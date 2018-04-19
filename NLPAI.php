<?php if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * @controller      NLPAI
 * @author          Anand Selvadurai
 * @added           2017.11.02
 * @updated author  none
 * @update date     none
 * @since           Version 1.0
 * @filesource      controllers/NLPAI
 */

ini_set('max_execution_time', 0);

class NLPAI extends CI_Controller
{

	/**
	 * $nlp
	 * 
	 * @var [type]
	 */
	public $nlp;

	/**
	 * __construct
	 * 
	 */
	public function __construct()
	{
		parent::__construct();

		$this->load->library('rest');
	}

	/**
	 * train
	 *
	 * train the NLP - AI model by selected Bot's intents
	 * 
	 * @return [type] [description]
	 */
	function train()
	{
		try {
			$_log = array();
			//+-- fetch training schedules process for the bots
			$train_data['limit'] 	= 10;
			$train_data['offset'] = 0;
	    $training_schedules 	= $this->rest->get('nlp/get_training_schedules', $train_data);
	    if($training_schedules->status && !empty($training_schedules->result)) {
	    	$training_schedules = $training_schedules->result;
	    	if(!empty($training_schedules)) {
	    		//+-- initiate NLP library class
	    		$this->nlp = new NLProcessing();

	    		foreach ($training_schedules as $index => $schedule) {
	    			$bot_id 	= $schedule->bot_id;
	    			$user_id 	= $schedule->user_id;
	    			$intents_json_name = 'intents_json_'. $bot_id . '.json';
	    			$_log[] = array('bot_name' => $schedule->bot_name, 'intent_json' => $intents_json_name);

	    			$get_intent_param['bot_id'] 	= $bot_id;
						$get_intent_param['user_id'] 	= $user_id;
				    $training_intents 		= $this->rest->get('nlp/get_training_intents', $get_intent_param);
				    if($training_intents->status && !empty($training_intents->result)) {
				    	$training_intents = $training_intents->result;
				    	if(!empty($training_intents)) {
				    		
				    		//+-- save the intents
				    		$this->nlp->save_intents($training_intents, $intents_json_name);

				    		//+-- train the intents
				    		$this->nlp->train_intents($intents_json_name);
				    		
				    		//+-- update the status once training completed for the scheduled intents training
				    		$update_param['user_id'] 	= $user_id;
				    		$update_param['bot_id'] 	= $bot_id;
				    		$this->rest->post('nlp/update_training_schedule', $update_param);
				    	}
				    }
	    		}
	    	}
	    }
	  } catch(Exception $ex) {}
	}
}
