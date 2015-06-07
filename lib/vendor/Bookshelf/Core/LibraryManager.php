<?php

namespace Bookshelf\Core;

use Bookshelf\DataType\Book;
use Bookshelf\DataType\BookMetadata;
use Bookshelf\DataIo\DatabaseConnection;
use Bookshelf\DataIo;

class LibraryManager {
    private $database_connection;
    private $config;

    public function __construct() {
        $this->database_connection = new DatabaseConnection();
        $this->config = new Configuration(true, $this->database_connection);
    }

    public function addBook($book) {
        $data['file_name'] = $book->original_name . '.' . $book->original_extension;
        $data['uuid'] = $book->uuid;
        $data['cover_image'] = $book->metadata->cover_image;
        $data['title'] = $book->metadata->title;
        $data['author'] = $book->metadata->author;
        $data['description'] = $book->metadata->description;
        $data['language'] = $book->metadata->language;
        $data['identifier'] = $book->metadata->identifier;
        $data['tags'] = implode(',', $book->metadata->tags);

        return $this->database_connection->insertBook($data);
    }

    // $to_update = array('property' => 'value');
    // e.g.: $to_update = array('title' => 'Some Book Title', 'author' => 'Some Author');
    public function updateBook($id, $to_update) {
        $this->database_connection->updateBook($id, $to_update);
    }

    public function deleteBook($id) {
        $this->database_connection->deleteBook($id);
        DataIo\FileManager::deleteBook($id);
    }

    // returns Book
    public function getBookById($id){
        $data = $this->database_connection->getBookById($id);
        $original_name = pathinfo($data['file_name'], PATHINFO_FILENAME);
        $original_extension = pathinfo($data['file_name'], PATHINFO_EXTENSION);

        $metadata = new BookMetadata();
        $metadata->cover_image = $data['cover_image'];
        $metadata->title = $data['title'];
        $metadata->author = $data['author'];
        $metadata->description = $data['description'];
        $metadata->language = $data['language'];
        $metadata->identifier = $data['identifier'];
        $metadata->categories = $data['categories'] == '' ? array() : explode(',', $data['categories']);

        $tags = str_replace(', ', ',', $data['tags']);
        $metadata->tags = $tags == '' ? array() : explode(',', $tags);

        return new Book($id, $data['uuid'], $original_name, $original_extension, $metadata);
    }


    // returns "int" (book ID)
    public function getBook($field, $query, $exact = false) {
        return $this->database_connection->getBook($field, $query, $exact);
    }

    // returns: array[Book]
    public function listBooks() {
        $data_array = $this->database_connection->dumpLibraryData();
        foreach($data_array as $data) {
            $original_name = pathinfo($data['file_name'], PATHINFO_FILENAME);
            $original_extension = pathinfo($data['file_name'], PATHINFO_EXTENSION);

            $metadata = new BookMetadata();
            $metadata->cover_image = $data['cover_image'];
            $metadata->title = $data['title'];
            $metadata->author = $data['author'];
            $metadata->description = $data['description'];
            $metadata->language = $data['language'];
            $metadata->identifier = $data['identifier'];
            $metadata->categories = $data['categories'] == '' ? array() : explode(',', $data['categories']);

            $tags = str_replace(', ', ',', $data['tags']);
            $metadata->tags = $tags == '' ? array() : explode(',', $tags);

            $result[] = new Book($data['id'], $data['uuid'], $original_name, $original_extension, $metadata);
        }
        return $result;
    }

    // $query: either 'homemade german plätzchen' or 'author:gabriele altpeter desc:plätzchen'
    // returns array[Book]
    public function search($query) {
        $query = preg_replace('/(author|desc|isbn|lang|tags):/i', '&$1=', $query);
        parse_str($query, $search);


    }
}
