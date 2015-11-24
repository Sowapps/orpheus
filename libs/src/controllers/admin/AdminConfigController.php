<?php

class AdminConfigController extends AdminController {
	
	/**
	 * @param HTTPRequest $request The input HTTP request
	 * @return HTTPResponse The output HTTP response
	 * @see HTTPController::run()
	 */
	public function run(HTTPRequest $request) {

		/* @var $USER User */
// 		GlobalConfig
		
// 		$formData = array();
		if( $data = $request->getArrayData('row') ) {
		
			try {
				$GlobalConfig	= GlobalConfig::instance();
				$GlobalConfig->set($data['key'], $data['value']);
		
			} catch(UserException $e) {
				reportError($e);
			}
		}
		
		return $this->renderHTML('app/admin_config', array(
		));
	}

}
