<?php
namespace core;

class ModelException extends \Exception
{
	public function __construct($message, $code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}

	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}<br/>";
	}

	public function customFunction() {
		echo "A custom function for this type of exception<br/>";
	}
}