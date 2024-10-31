<?php

namespace MyFastApp;

class ErrorMessage
{
    public $Message = null;

    function __construct($message) {
        $this->Message = $message;
    }    
}
