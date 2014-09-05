<?php
//! The unknown key exception class
/*!
	This exception is thrown when an important key is not found
*/
class UnknownKeyException extends Exception {
	
	//Attributs
	private $key;
	
	//Methodes
	
	//! Constructor
	/*!
	 * \param $message The message.
	 * \param $key The unknown key.
	 */
	public function __construct($message, $key) {
		parent::__construct($message, 1002);
		$this->key = (string) $key;
	}
	
	//! Gets the unknown key
	/*!
	 * \return The key.
	 */
	public function getKey() {
		return $this->key;
	}
}
