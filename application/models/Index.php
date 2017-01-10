<?php

class IndexModel extends \core\model
{
	public function __construct(){
		$this->init();
	}

	public function getPageList($page=1){
		return [
			['id'=>1,'info'=>'message 1'],
			['id'=>2,'info'=>'message 2'],
			['id'=>3,'info'=>'message 3'],
			['id'=>4,'info'=>'message 4'],
			['id'=>5,'info'=>'message 5'],
		];
	}

	public function getUserbyId($id){
		return ['id'=>$id,'name'=>'km3945-'.$id];
	}

	public function getMessbyId($id){
		return ['id'=>$id,'mess'=>'message-'.$id];
	}

	public function getRows(){
		$sql='select * from `dkid` limit 1';
		$rs=$this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		return $rs;
	}

	public function newRow(){
		$sid = dk_get_next_id();
		$this->db->insert('dkid', ['f_id' => $sid]);
		$rs=$this->db->count('dkid');
		return $rs;
	}
}
