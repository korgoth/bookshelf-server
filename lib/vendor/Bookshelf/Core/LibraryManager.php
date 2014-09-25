<?php

namespace Bookshelf\Core;

use Bookshelf\DataType\BookMetadata;
use Bookshelf\DataIo\DatabaseConnection;
use Bookshelf\DataIo;

class LibraryManager {
    private $database_connection;
    
    function __construct() {
        $this->database_connection = new DatabaseConnection();
    }
<<<<<<< HEAD
    
    function addBook($file_name, $metadata = new BookMetadata()){
=======

    function addBook($filename, $metadata = null) {
        // unfortunately, there is no other way to set a parameter default value to an expression: http://stackoverflow.com/a/5859401
        if($metadata === null) {
            $metadata = new BookMetadata();
        }

        require Application::LIB_DIR . 'config.php';

>>>>>>> 2517c36f500501c88b58b664d37121f0594eb826
        $file_hash = DataIo\FileManager::hash($LIBRARY_DIR . '/' . $filename);
        $args = array('file_name' => "'" . $file_name . "'",
                      'file_hash' => "'" . $file_hash . "'");
        
        $args .= $metadata->getArray();
        foreach($args as $key => $value) {
            $args[$key] = "'" . $value . "'";
        }
        
<<<<<<< HEAD
        $this->database_connection->insertLibraryData($args)
    }
    
    function getBook($file_name, $file_hash) {
        $result = $this->database_connection->selectLibraryData(array('file_name' => $file_name, 
                                                                      'file_hash' => $file_hash));
        return (empty($result[0]) ? -1 : $result[0]);
    }
    
    function listBooks() {
        $result = $this->database_connection->selectLibraryData(NULL, array('file_name', 'file_hash', 'title'));
        return (empty($result[0]) ? -1 : $result[0]);
=======
        $this->database_connection->insertLibraryData($args);
>>>>>>> 2517c36f500501c88b58b664d37121f0594eb826
    }
}