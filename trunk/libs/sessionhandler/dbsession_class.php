<?php

/*
CREATE TABLE `orpheus_demo`.`sessions` (
	`id` INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`sessid` VARCHAR( 128 ) NOT NULL COMMENT 'The max length of hash algorithms. You can set it according to your php configuration.',
	`data` LONGTEXT NOT NULL ,
	`create_time` INT( 10 ) UNSIGNED NOT NULL ,
	`create_ip` VARCHAR( 40 ) NOT NULL ,
	`edit_time` INT( 10 ) UNSIGNED NOT NULL ,
	INDEX ( `sessid` )
) ENGINE =  MYISAM;
*/

class DBSession extends PermanentObject implements SessionInterface {
	//Attributes
	protected static $table = 'sessions';
	protected static $domain = 'sessions';
	//protected static $status = array('approved'=>array('rejected'), 'rejected'=>array('approved'));
	protected static $fields = array(
			'id', 'sessid', 'data', 'create_time', 'create_ip', 'edit_time'
	);
	protected static $editableFields = array('sessid', 'data', 'edit_time');
	protected static $validator = array();
	
	public function writeData($data) {
		$this->data = $session_data;
		$this->edit_time = time();
	}
	
	public function readData() {
		return $this->data;
	}
	
	public function sessID() {
		return $this->sessid;
	}
	
// 	public function save();
	
	public static function build($session_id) {
		log_debug($id = Session::create(array('sessid'=>$session_id, 'edit_time'=>time())));
		return Session::load($id);
	}
	
	public static function getBySessID($session_id) {
		return static::get(array(
				'where' => 'sessid='.SQLAdapter::quote($session_id),
				'number' => 1,
		));
	}
	public static function deleteBySessID($session_id) {
		return static::delete(array(
			'where' => 'sessid='.SQLAdapter::quote($session_id),
			'number' => 1,
		));
	}
	public static function deleteFrom($delay) {
		return static::delete(array(
			'where' => 'edit_time < '.(time()-intval($delay))
		));
	}
}

class Session extends DBSession {}