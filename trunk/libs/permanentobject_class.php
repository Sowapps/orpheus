<?php
//! The Permanent Object class
/*!
	Create permanent object using the SQL Mapper.
*/
abstract class PermanentObject {
	
	//Attributes
	protected static $table = null;
	protected static $fields = array();
	protected static $userEditableFields = array();
	
	protected static $IDFIELD = 'id';
	protected $modFields = array();
	protected $data = array();
	
	// *** OVERLOADED METHODS ***
	
	//! Constructor
	/*!
		\param $data An array of the object's data to construct. 
	 */
	public function __construct(array $data) {
		foreach( static::$fields as $fieldname ) {
			if( !isset($data[$fieldname]) ) {
				throw new FieldNotFoundException($fieldname);
			}
			$this->data[$fieldname] = $data[$fieldname];
		}
		$this->modFields = array();
	}
	
	//! Magic getter
	/*!
		\param $name Name of the property to get.
		\return The value of field $name.
		
		Get the value of field $name.
	*/
	public function __get($name) {
		try {
			return $this->getValue($name);
		} catch(FieldNotFoundException $e) {
			/* Previously, we got the attribute if
			 * the data does not exist but private is private.
			 */
			throw $e;
		}
	}
	
	//! Magic setter
	/*!
		\param $name Name of the property to set.
		\param $value New value of the property.
		
		Set the value of field $name.
	*/
	public function __set($name, $value) {
		try {
			$this->setValue($name, $value);
		} catch(FieldNotFoundException $e) {
			/* Previously, we set the attribute if
			 * the data does not exist but private is private.
			 */
			throw $e;
		}
	}
	
	//! Destructor
	/*!
		If something was modified, it saves the new data.
	*/
	public function __destruct() {
		if( !empty($this->modFields) ) {
			try {
				$this->save();
			} catch(Exception $e) {
				text('An error occured while saving (__destruct):');
				text($e->getMessage());
			}
		}
	}
	
	//! Magic toString
	/*!
		\return The string value of the object.
		
		The object's value when casting to string.
	*/
	public function __toString() {
		return '#'.$this->{static::$IDFIELD}.' ('.static::getClass().')';
	}
	
	// *** USER METHODS ***
	
	//! Update object
	/*!
		\param $uInputData The input data we will check and extract, used by children.
		\param $data The data from wich it will update this object, used by parents, including this one.
		\return 1 in case of success, else 0.
		\sa runForUpdate()
		
		This method require to be overrided but it still be called too by the child classes.
		Here $uInputData is not used, it is reserved for child classes.
		$data must contain a filled array of new data.
		This method update the EDIT event log.
		Before saving, runForUpdate() is called to let child classes to run custom instructions.
	*/
	public function update($uInputData, array $data=array()) {
		try {
			if( empty($data) ) {
				throw new UserException('updateEmptyData');
			}
			static::checkForEntry(static::completeFields($data));
		} catch(UserException $e) { addUserError($e); return 0; }
		
		foreach($data as $fieldname => $fieldvalue) {
			if( $fieldname != static::$IDFIELD && in_array($fieldname, static::$userEditableFields) ) {
				$this->$fieldname = $fieldvalue;
			}
		}
		if( in_array('edit_time', static::$fields) ) {
			static::logEvent('edit');
		}
		$this->runForUpdate();
		return $this->save();
	}
	
	//! Run for Update
	/*!
		\sa update()
		
		This function is called by update() before saving new data.
		In this base class, this method does nothing.
	*/
	public function runForUpdate() { }
	
	//! Save object
	/*!
		\return 1 in case of success, else 0.
		
		If some fields was modified, it saves these fields using the SQL Mapper.
	*/
	public function save() {
		if( empty($this->modFields) ) {
			return 0;
		}
		$updQ = '';
		foreach($this->modFields as $fieldname) {
			if( $fieldname != static::$IDFIELD ) {
				$updQ .= ( (!empty($updQ)) ? ', ' : '').$fieldname.'='.pdo_quote($this->$fieldname);
			}
		}
		$IDFIELD=static::$IDFIELD;
		$this->modFields = array();
		$options = array(
			'what'	=> $updQ,
			'table'	=> static::$table,
			'where'	=> "{$IDFIELD}={$this->{$IDFIELD}}",
			'number'=> 1,
		);
		return SQLMapper::doUpdate($options);
	}
	
	//! Mark the field as modified
	/*!
		\param $field The field to mark as modified.
		
		Add the $field to the modified fields array.
	*/
	private function addModFields($field) {
		if( !in_array($field, $this->modFields) ) {
			$this->modFields[] = $field;
		}
	}
	
	//! Get one value or all values.
	/*!
		\param $key Name of the field to get.
		
		Get the value of field $key or all data values if $key is null.
	*/
	public function getValue($key=null) {
		if( !empty($key) ) {
			if( !in_array($key, static::$fields) ) {
				throw new FieldNotFoundException($key);
			}
			return $this->data[$key];
		}
		return $this->data;
	}
	
	//! Set the value of a field
	/*!
		\param $key Name of the field to set.
		\param $value New value of the field.
		
		Set the field $key with the new $value.
	*/
	public function setValue($key, $value) {
		if( !isset($key, $value) ) {
			throw new UserException("nullValue");
			
		} else if( !in_array($key, static::$fields) ) {
			throw new FieldNotFoundException($key);
			
		} else {
			if( empty($this->data[$key]) || $value !== $this->data[$key] ) {
				$this->addModFields($key);
				$this->data[$key] = $value;
			}
		}
	}
	
	//! Verify equality
	/*!
		\param $o The object to compare.
		\return True if this object represents the same data, else False.
		
		Compare the class and the ID field value of the 2 objects.
	*/
	public function equals(PermanentObject $o) {
		return (static::getClass()==$o::getClass() && $this->{static::$IDFIELD}==$o->{static::$IDFIELD});
	}
	
	//! Log an event
	/*!
		\param $event The event to log in this object.
		\param $time A specified time to use for logging event.
		\param $ipAdd A specified IP Adress to use for logging event.
		\sa getLogEvent()
		
		Compare the class and the ID field value of the 2 objects.
	*/
	public function logEvent($event, $time=null, $ipAdd=null) {
		$log = static::getLogEvent($event, $time, $ipAdd);
		$this->setValue($event.'_time', $log[$event.'_time']);
		$this->setValue($event.'_ip', $log[$event.'_ip']);
	}
	
	// *** STATIC METHODS ***
	
	public static function load($id) {
		if( !ctype_digit("$id") ) {
			throw new UserException('invalidID');
		}
		$IDFIELD=static::$IDFIELD;
		$options = array(
			'table'	=> static::$table,
			'number'=> 1,
			'where'	=> "{$IDFIELD}={$id}",
		);
		$data = SQLMapper::doSelect($options);
		if( empty($data) ) {
			throw new UserException('inexistantEntry');
		}
		return new static($data[0]);
	}
	
	public static function delete($id) {
		if( !ctype_digit("$id") ) {
			throw new UserException('invalidID');
		}
		$IDFIELD=static::$IDFIELD;
		$options = array(
			'table'	=> static::$table,
			'number'=> 1,
			'where'	=> "{$IDFIELD}={$id}",
		);
		return SQLMapper::doDelete($options);
	}
	
	public static function get(array $options=array()) {
		$options['table'] = static::$table;
		return SQLMapper::doSelect($options);
	}
	
	public static function create($inputData) {
		$data = static::checkUserInput($inputData);
		
		if( in_array('create_time', static::$fields) ) {
			$data += static::getLogEvent('create');
		}
		//Check if entry already exist
		static::checkForEntry($data);
		//Other Checks and to do before insertion
		static::runForEntry($data);
		
		$insertQ = '';
		foreach($data as $fieldname => $fieldvalue) {
			$insertQ .= ( (!empty($insertQ)) ? ', ' : '').$fieldname.'='.pdo_quote($fieldvalue);
		}
		$options = array(
			'table'	=> static::$table,
			'what'=> $insertQ,
		);
		SQLMapper::doInsert($options);
		$LastInsert = pdo_query("SELECT LAST_INSERT_ID();", PDOFETCHFIRSTCOL);
		//To do after insertion
		static::applyToEntry($data, $LastInsert);//old ['LAST_INSERT_ID()']
		return $LastInsert;
	}
	
	public static function completeFields($data) {
		foreach( static::$fields as $fieldname ) {
			if( !isset($data[$fieldname]) ) {
				$data[$fieldname] = '';
			}
		}
		return $data;
	}
	
	public static function getLogEvent($event, $time=null, $addIP=null) {
		if( !isset($time) ) {
			$time=time();
		}
		if( !isset($addIP) ) {
			$addIP=$_SERVER['REMOTE_ADDR'];
		}
		return array($event.'_time' => $time, $event.'_ip' => $addIP);
	}
	
	public static function getTable() {
		return static::$table;
	}
	
	public static function getIDField() {
		return static::$IDFIELD;
	}
	
	public static function getClass() {
		return __CLASS__;
	}
	
	public static function runForEntry(&$data) { }
	
	public static function applyToEntry(&$data, $id) { }
	
	// 		** CHECK METHODS **
	
	public static function checkUserInput($uInputData) { }
	
	public static function checkForEntry($data) { }
}
?>