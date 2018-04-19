<?php defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'/libraries/REST_Controller.php';

/**
 * @controller      NLP
 * @author          Anand Selvadurai
 * @added           2017.11.02
 * @updated author  none
 * @update date     none
 * @since           Version 1.0
 * @filesource      api/NLP
 */
class NLP extends REST_Controller
{
    
  public $headercheck;

  /**
   * __construct
   */
  function __construct()
  {
    parent::__construct();
    //+-- load helper
    $this->load->helper('header');
    $this->headercheck  = check_valid_key();
  }

  /**
   * get_training_schedules_get
   *
   * get training schedules for all the bots
   * 
   * @return [json]
   */
  function get_training_schedules_get()
  {
    $get_data      = $this->input->get();
    if($this->headercheck) {
      $training_schedules = $this->nlp_model->get_training_schedules($get_data);
      if(!empty($training_schedules)) {
        $this->response(array('status' => '1', 'result'=> $training_schedules), 200);
      } else {
        $this->response(array('status' => '1', 'result'=> ''), 200);
      }
    } else {
      $this->response(array('error' => 'You are not authorized to access.','status' => '0', 'result'=>false), 404);
    }
  }

  /**
   * add_training_schedules_post
   *
   * add training schedules for a bot
   * 
   * @return [json]
   */
  function add_training_schedules_post()
  {
    $post_data      = $this->input->post();
    if($this->headercheck) {
      $training_schedules = $this->nlp_model->add_training_schedule($post_data);
      if(!empty($training_schedules)) {
        $this->response(array('status' => '1', 'result'=> $training_schedules), 200);
      } else {
        $this->response(array('status' => '1', 'result'=> ''), 200);
      }
    } else {
      $this->response(array('error' => 'You are not authorized to access.','status' => '0', 'result'=>false), 404);
    }
  }

  /**
   * get_training_intents_get
   *
   * get training intents and its questions for speecific bot
   * 
   * @return [json]
   */
  function get_training_intents_get()
  {
    $get_data      = $this->input->get();
    if($this->headercheck) {
      $training_intents = $this->nlp_model->get_training_intents($get_data);
      if(!empty($training_intents)) {
        $this->response(array('status' => '1', 'result'=> $training_intents), 200);
      } else {
        $this->response(array('status' => '1', 'result'=> ''), 200);
      }
    } else {
      $this->response(array('error' => 'You are not authorized to access.','status' => '0', 'result'=>false), 404);
    }
  }

  /**
   * update_training_schedule_post
   *
   * update training intent for specific bot
   * 
   * @return [json]
   */
  function update_training_schedule_post()
  {
    $post_data      = $this->input->post();
    if($this->headercheck) {
      $training_schedules = $this->nlp_model->update_training_schedule($post_data);
      if(!empty($training_schedules)) {
        $this->response(array('status' => '1', 'result'=> 'Training status updated successfully.'), 200);
      } else {
        $this->response(array('status' => '1', 'result'=> ''), 200);
      }
    } else {
      $this->response(array('error' => 'You are not authorized to access.','status' => '0', 'result'=>false), 404);
    }
  }

  /**
   * train_bot_post
   *
   * train the bot's intent on demand
   * 
   * @return [json]
   */
  function train_bot_post()
  {
    $post_data        = $this->input->post();
    $training_result  = false;
    if($this->headercheck) {
      $training_intents = $this->nlp_model->get_training_intents($post_data);

      if(!empty($training_intents)) {
        $nlp = new NLProcessing();
        //+-- form bot's intents json name
        $intents_json_name = 'intents_json_'. $post_data['bot_id'] . '.json';
        //+-- save the intents
        $intent_response    = $nlp->save_intents($training_intents, $intents_json_name);

        if($intent_response) {
          //+-- train the intents
          $training_result  = $nlp->train_intents($intents_json_name, $this);
        }
        if($training_result) {
          $post_data['trained_type']    = 'M';
          $post_data['completed_date']  = gmdate('Y-m-d H:i:s');

          //+-- add trained tracking entry
          $this->nlp_model->add_training_schedule($post_data);

          $this->response(array('status' => 1, 'result'=> 'NLP AI successfully trained for your Chatbot.'), 200);

        } else {
          $this->response(array('status' => 0, 'result'=> 'Problem occurred while training the NLP AI.'), 200);
        }
      } else {
        $this->response(array('status' => 2, 'result'=> 'Please enter at-least one message for your keyword to start the training.'), 200);  
      }
    } else {
      $this->response(array('status' => 0, 'result'=> 'You are not authorized to access.'), 404);
    }
  }

  /**
   * get_intent_post
   *
   * get intent for the user message
   * 
   * @return [json]
   */
  function get_intent_post()
  {
    $post_data        = $this->input->post();
    $training_result  = false;
    $bot_response     = '';
    $bot_id           = 0;
    if($this->headercheck) {
      if(!isset($post_data['bot_id']) && empty($post_data['bot_id'])) {
        $bot_detail = $this->nlp_model->get_bot_detail($post_data);
        $bot_id     = $bot_detail['bot_id'];
      } else {
        $bot_id     = $post_data['bot_id'];
      }
      if(!empty($bot_id)) {
        $nlp = new NLProcessing();
        //+-- form bot's intents json name
        $intents_json_name = 'intents_json_'. $bot_id . '.json';
        
        $intents_file       = __DIR__ .'/../../../nlp/saved_intents/'. $intents_json_name;
        $model_file         = __DIR__ .'/../../../nlp/saved_models/'. $intents_json_name .'.training_data';
        if (file_exists($intents_file) && file_exists($model_file)) {
          $post_data['intents_json_name'] = $intents_json_name;
          //+-- train the intents
          $intent_uid  = $nlp->get_intents($post_data);
        } else {
          $intent_uid  = 'default';
        }
        //+-- if NLP permission enabled but not trained the Bot then continue MySQL-NLP process
        if($intent_uid == 'default') {
          if(!empty($post_data['widget_uid'])) {
            $bot_response = $this->process_message_model->get_chat_widget_answer($post_data);
          } else if(!empty($post_data['bot_id'])) {
            $user_psid = isset($post_data['user_psid']) ? $post_data['user_psid'] : NULL;
            $bot_response = $this->process_message_model->get_bot_answer($post_data['bot_id'], $post_data['message'], $post_data['message_type'], $user_psid);
          } else {
            $bot_response = $this->process_message_model->get_user_answer($post_data);
          }
        } else {
          $post_data['user_message'] = $intent_uid;
          $post_data['message_type'] = 'NLP';
          $post_data['bot_id']       = $bot_id;
          $bot_response = $this->process_message_model->get_user_answer($post_data);
        }
        $this->response(array('status' => 1, 'result'=> $bot_response), 200);

      } else {
        $this->response(array('status' => 0, 'result'=> 'No valid record found.'), 200);  
      }
    } else {
      $this->response(array('error' => 'You are not authorized to access.','status' => '0', 'result'=>false), 404);
    }
  }

  /**
   * get_nlp_permission_get
   *
   * get NLP permission for the package
   * 
   * @return [json]
   */
  function get_nlp_permission_get()
  {
    $get_data      = $this->input->get();
    if($this->headercheck) {
      $nlp_permission = $this->nlp_model->get_nlp_permission($get_data);
      if(!empty($nlp_permission)) {
        $this->response(array('status' => '1', 'result'=> $nlp_permission), 200);
      } else {
        $this->response(array('status' => '1', 'result'=> ''), 200);
      }
    } else {
      $this->response(array('error' => 'You are not authorized to access.','status' => '0', 'result'=>false), 404);
    }
  }

}