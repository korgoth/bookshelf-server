<?php

namespace Bookshelf\DataIo;

use Bookshelf\Core\Application;
use Bookshelf\Core\Configuration;
use Bookshelf\Utility\ErrorHandler;
use Bookshelf\Utility\ErrorLevel;

class DatabaseConnection {
    private static $ALLOWED_BOOK_PROPERTIES = array('file_name', 'uuid', 'cover_image', 'title', 'author', 'description', 'language', 'identifier', 'tags');

    private $mysqli;
    private $config;

    public function __construct() {
        $this->config = new Configuration(false);

        $this->mysqli = new \mysqli($this->config->getDatabaseHost(), $this->config->getDatabaseUser(), $this->config->getDatabasePassword(), $this->config->getDatabaseName());

        if($this->mysqli->connect_errno) {
            ErrorHandler::throwError('Could not connect to database.', ErrorLevel::CRITICAL);
            return false;
        }
    }

    // Generally, these functions should not be accessed directly but rather be proxied by a more specific class.

    // returns string
    public function readConfigValue($property) {
        if($result = $this->mysqli->query('SELECT value FROM config WHERE property LIKE \'' . $property .'\'')) {
            return $result->fetch_array(MYSQL_ASSOC)['value'];
        }
        else {
            ErrorHandler::throwError('Reading config value for property ' . $property . ' failed.', ErrorLevel::DEBUG);
        }
        return false;
    }

    // returns bool
    public function validateUser($username, $password) {
        if($result = $this->mysqli->query("SELECT passwd_hash FROM users WHERE username='$username'")) {
           $row = $result->fetch_array();
            return hash('sha256', $password . $this->config->getSalt()) == $row['passwd_hash'];
        }
        else {
            ErrorHandler::throwError('Validating user ' . $username . ' failed.', ErrorLevel::DEBUG);
        }
        return false;
    }

    // should not be called directly, only use from LibraryManager::addBook
    // returns int (ID)
    public function insertBook($data, $categories) {
        foreach($data as $property => $value) {
            $value = $this->purify($value);
            $data[$property] = $this->escape($value);
        }

        $query = "BEGIN;
        INSERT INTO library (file_name, uuid, cover_image, title, author, description, language, identifier, tags)
VALUES ('{$data['file_name']}', '{$data['uuid']}', '{$data['cover_image']}', '{$data['title']}', '{$data['author']}', '{$data['description']}', '{$data['language']}', '{$data['identifier']}', '{$data['tags']}');
        SELECT LAST_INSERT_ID() INTO @book_id;";

        foreach($categories as $category) {
            $category = $this->escape($this->purify($category));
            $query .= "INSERT INTO categories (name) SELECT * FROM  (SELECT '{$category}') AS tmp WHERE NOT EXISTS (SELECT name FROM categories WHERE name = '{$category}') LIMIT 1;
INSERT INTO category_relationships (book, category) SELECT @book_id, categories.id FROM categories WHERE categories.name = '{$category}';";
        }
        $query .= "COMMIT;
        SELECT @book_id;";

        if($this->mysqli->multi_query($query)) {
            while($this->mysqli->more_results()) {
                $this->mysqli->next_result();
                $result = $this->mysqli->store_result();
            }
            return $result->fetch_row()[0];
        }
        else {
            ErrorHandler::throwError('Inserting book ' . $data['file_name'] . ' failed.', ErrorLevel::DEBUG);
        }
        return false;
    }

    // void
    public function deleteBook($id) {
        $query = "BEGIN;
DELETE FROM library WHERE id={$id};
DELETE FROM category_relationships WHERE book = {$id};
COMMIT;";
        $this->mysqli->multi_query($query);
    }

    // void
    public function updateBook($id, $to_update) {
        $query = 'UPDATE library SET';

        foreach($to_update as $property => $value) {
            if(in_array($property, DatabaseConnection::$ALLOWED_BOOK_PROPERTIES, true)) {
                $value = $this->purify($value);
                $value = $this->escape($value);

                // First item does not need a comma
                if ($value === reset($to_update)) {
                    $query .= " {$property} = '{$value}'";
                } else {
                    $query .= ", {$property} = '{$value}'";
                }
            } else {
                unset($to_update[$property]);
            }
        }

        $query .= " WHERE id = {$id};";


        if(!$this->mysqli->query($query)) {
            ErrorHandler::throwError('Updating book ' . $id . ' failed.<br>Query: ' . $query . '<br>MySQL error: '. $this->mysqli->error, ErrorLevel::DEBUG);
        }
    }

    //void
    public function updateBookCategories($id, $categories) {
        $query = "BEGIN;
        DELETE FROM category_relationships WHERE book = {$id};";

        foreach($categories as $category) {
            $category = $this->escape($this->purify($category));
            $query .= "INSERT INTO categories (name) SELECT * FROM  (SELECT '{$category}') AS tmp WHERE NOT EXISTS (SELECT name FROM categories WHERE name = '{$category}') LIMIT 1;
INSERT INTO category_relationships (book, category) SELECT {$id}, categories.id FROM categories WHERE categories.name = '{$category}';";
        }

        $query .= 'COMMIT;';

        if(!$this->mysqli->multi_query($query)) {
            ErrorHandler::throwError('Updating categories for book ' . $id . ' failed.<br>Query: ' . $query . '<br>MySQL error: '. $this->mysqli->error, ErrorLevel::DEBUG);
        }

        while($this->mysqli->more_results()) {
            $this->mysqli->next_result();
            $this->mysqli->store_result();
        }
    }

    // returns array('property' => 'value')
    public function getBookById($id) {
        $query = "SELECT library.*, GROUP_CONCAT(categories.name) AS categories FROM library
LEFT JOIN category_relationships ON category_relationships.book = library.id
LEFT JOIN categories ON category_relationships.category = categories.id
WHERE library.id = {$id}";

        if($result = $this->mysqli->query($query)) {
            return $result->fetch_array(MYSQL_ASSOC);
        }
        else {
            ErrorHandler::throwError('Getting book with ID ' . $id . ' failed.', ErrorLevel::DEBUG);
        }
        return -1;
    }

    // returns int (ID)
    public function getBook($field, $query, $exact = false) {
        if(!$exact) {
            if($result = $this->mysqli->query("SELECT id FROM library WHERE {$field} LIKE '%{$query}%' LIMIT 1")) return $result->fetch_array(MYSQL_ASSOC)['id'];
        }
        else {
            if($result = $this->mysqli->query("SELECT id FROM library WHERE {$field}='{$query}'")) return $result->fetch_array(MYSQL_ASSOC)['id'];

        }
        return -1;
    }

    // returns array(array('property' => 'value'))
    public function dumpLibraryData() {
        $query = 'SELECT library.*, GROUP_CONCAT(categories.name) AS categories FROM library
LEFT JOIN category_relationships ON category_relationships.book = library.id
LEFT JOIN categories ON category_relationships.category = categories.id
GROUP BY library.id';

        if($result = $this->mysqli->query($query)) return $this->fetch_all($result);
    }

    // returns string
    public function escape($string) {
        return $this->mysqli->real_escape_string($string);
    }

    // returns string
    public function purify($string) {
        // Purify HTML in order to avoid malicious code execution and improper HTML
        // TODO: Make make include path configurable
        require_once 'HTMLPurifier.auto.php';
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', Application::ROOT_DIR . '/cache/HTMLPurifier');
        $purifier = new \HTMLPurifier($config);

        return $purifier->purify($string);
    }

    // returns array
    private function fetch_all($result) {
        $all = array();
        while($row = $result->fetch_array(MYSQL_ASSOC)){
            $all[] = $row;
        }
        return $all;
    }
}
