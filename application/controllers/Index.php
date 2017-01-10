<?php
class IndexController extends \core\controllerIndex
{
	public function indexAction(){
		try {
			$argv = $this->getData();
			$m=new IndexModel();
			$rs=$m->getPageList($argv['page']);
			$this->setBody($rs);
		} catch (\Exception $e) {
			$this->setErr($e,__METHOD__);
		}
	}
	public function index2Action(){
		try {
			$argv = $this->getData();
			$m=new IndexModel();
			$rs=$m->getUserbyId($argv['id']);
			$this->setBody($rs);
		} catch (\Exception $e) {
			$this->setErr($e,__METHOD__);
		}
	}
	public function index3Action(){
		try {
			$argv = $this->getData();
			$m=new IndexModel();
			$rs=$m->getMessbyId($argv['id']);
			$this->setBody($rs);
		} catch (\Exception $e) {
			$this->setErr($e,__METHOD__);
		}
	}
}