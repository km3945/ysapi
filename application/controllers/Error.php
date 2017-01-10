<?php
class ErrorController extends \core\controllerIndex {

	public function errorAction($exception) {
		/** @var $exception \Yaf\Exception */
		throw $exception;
	}
}