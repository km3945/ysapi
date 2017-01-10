<?php
namespace core;

use \publics as pub;

class model {
	/** @var \medoo */
	public $db;

	public function init(){
		$this->db= pub::getDB('ubuntu');
	}
}