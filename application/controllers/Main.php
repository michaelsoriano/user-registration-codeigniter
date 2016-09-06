<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Main extends CI_Controller {
        
        public $status; 
        public $roles;
    
        function __construct(){
            parent::__construct();
            $this->load->model('User_model', 'user_model', TRUE);
            $this->load->library('form_validation');    
            $this->form_validation->set_error_delimiters('<div class="error">', '</div>');
            $this->status = $this->config->item('status'); 
            $this->roles = $this->config->item('roles');
        }      
    
	public function index()
	{   
            if(empty($this->session->userdata['email'])){
                redirect(site_url().'/main/login/');
            }            
            /*front page*/
            $data = $this->session->userdata; 
            $this->load->view('header');            
            $this->load->view('index', $data);
            $this->load->view('footer');
	}
        
        
        public function register()
        {
             
            $this->form_validation->set_rules('firstname', 'First Name', 'required');
            $this->form_validation->set_rules('lastname', 'Last Name', 'required');    
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email');    
                       
            if ($this->form_validation->run() == FALSE) {   
                $this->load->view('header');
                $this->load->view('register');
                $this->load->view('footer');
            }else{                
                if($this->user_model->isDuplicate($this->input->post('email'))){
                    $this->session->set_flashdata('flash_message', 'User email already exists');
                    redirect(site_url().'main/login');
                }else{
                    
                    $clean = $this->security->xss_clean($this->input->post(NULL, TRUE));
                    $id = $this->user_model->insertUser($clean); 
                    $token = $this->user_model->insertToken($id);                                        
                    
                    $qstring = $this->base64url_encode($token);                    
                    $url = site_url() . 'main/complete/token/' . $qstring;
                    $link = '<a href="' . $url . '">' . $url . '</a>'; 
                               
                    $message = '';                     
                    $message .= '<strong>You have signed up with our website</strong><br>';
                    $message .= '<strong>Please click:</strong> ' . $link;                          

                    echo $message; //send this in email
                    exit;
                     
                    
                };              
            }
        }
        
        
        protected function _islocal(){
            return strpos($_SERVER['HTTP_HOST'], 'local');
        }
        
        public function complete()
        {                                   
            $token = base64_decode($this->uri->segment(4));       
            $cleanToken = $this->security->xss_clean($token);
            
            $user_info = $this->user_model->isTokenValid($cleanToken); //either false or array();           
            
            if(!$user_info){
                $this->session->set_flashdata('flash_message', 'Token is invalid or expired');
                redirect(site_url().'main/login');
            }            
            $data = array(
                'firstName'=> $user_info->first_name, 
                'email'=>$user_info->email, 
                'user_id'=>$user_info->id, 
                'token'=>$this->base64url_encode($token)
            );
           
            $this->form_validation->set_rules('password', 'Password', 'required|min_length[5]');
            $this->form_validation->set_rules('passconf', 'Password Confirmation', 'required|matches[password]');              
            
            if ($this->form_validation->run() == FALSE) {   
                $this->load->view('header');
                $this->load->view('complete', $data);
                $this->load->view('footer');
            }else{
                
                $this->load->library('password');                 
                $post = $this->input->post(NULL, TRUE);
                
                $cleanPost = $this->security->xss_clean($post);
                
                $hashed = $this->password->create_hash($cleanPost['password']);                
                $cleanPost['password'] = $hashed;
                unset($cleanPost['passconf']);
                $userInfo = $this->user_model->updateUserInfo($cleanPost);
                
                if(!$userInfo){
                    $this->session->set_flashdata('flash_message', 'There was a problem updating your record');
                    redirect(site_url().'main/login');
                }
                
                unset($userInfo->password);
                
                foreach($userInfo as $key=>$val){
                    $this->session->set_userdata($key, $val);
                }
                redirect(site_url().'main/');
                
            }
        }
        
        public function login()
        {
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email');    
            $this->form_validation->set_rules('password', 'Password', 'required'); 
            
            if($this->form_validation->run() == FALSE) {
                $this->load->view('header');
                $this->load->view('login');
                $this->load->view('footer');
            }else{
                
                $post = $this->input->post();  
                $clean = $this->security->xss_clean($post);
                
                $userInfo = $this->user_model->checkLogin($clean);
                
                if(!$userInfo){
                    $this->session->set_flashdata('flash_message', 'The login was unsucessful');
                    redirect(site_url().'main/login');
                }                
                foreach($userInfo as $key=>$val){
                    $this->session->set_userdata($key, $val);
                }
                redirect(site_url().'main/');
            }
            
        }
        
        public function logout()
        {
            $this->session->sess_destroy();
            redirect(site_url().'main/login/');
        }
        
        public function forgot()
        {
            
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email'); 
            
            if($this->form_validation->run() == FALSE) {
                $this->load->view('header');
                $this->load->view('forgot');
                $this->load->view('footer');
            }else{
                $email = $this->input->post('email');  
                $clean = $this->security->xss_clean($email);
                $userInfo = $this->user_model->getUserInfoByEmail($clean);
                
                if(!$userInfo){
                    $this->session->set_flashdata('flash_message', 'We cant find your email address');
                    redirect(site_url().'main/login');
                }   
                
                if($userInfo->status != $this->status[1]){ //if status is not approved
                    $this->session->set_flashdata('flash_message', 'Your account is not in approved status');
                    redirect(site_url().'main/login');
                }
                
                //build token 
				
                $token = $this->user_model->insertToken($userInfo->id);                        
                $qstring = $this->base64url_encode($token);                  
                $url = site_url() . 'main/reset_password/token/' . $qstring;
                $link = '<a href="' . $url . '">' . $url . '</a>'; 
                
                $message = '';                     
                $message .= '<strong>A password reset has been requested for this email account</strong><br>';
                $message .= '<strong>Please click:</strong> ' . $link;             

                echo $message; //send this through mail
                exit;
                
            }
            
        }
        
        public function reset_password()
        {
            $token = $this->base64url_decode($this->uri->segment(4));                  
            $cleanToken = $this->security->xss_clean($token);
            
            $user_info = $this->user_model->isTokenValid($cleanToken); //either false or array();               
            
            if(!$user_info){
                $this->session->set_flashdata('flash_message', 'Token is invalid or expired');
                redirect(site_url().'main/login');
            }            
            $data = array(
                'firstName'=> $user_info->first_name, 
                'email'=>$user_info->email, 
//                'user_id'=>$user_info->id, 
                'token'=>$this->base64url_encode($token)
            );
           
            $this->form_validation->set_rules('password', 'Password', 'required|min_length[5]');
            $this->form_validation->set_rules('passconf', 'Password Confirmation', 'required|matches[password]');              
            
            if ($this->form_validation->run() == FALSE) {   
                $this->load->view('header');
                $this->load->view('reset_password', $data);
                $this->load->view('footer');
            }else{
                                
                $this->load->library('password');                 
                $post = $this->input->post(NULL, TRUE);                
                $cleanPost = $this->security->xss_clean($post);                
                $hashed = $this->password->create_hash($cleanPost['password']);                
                $cleanPost['password'] = $hashed;
                $cleanPost['user_id'] = $user_info->id;
                unset($cleanPost['passconf']);                
                if(!$this->user_model->updatePassword($cleanPost)){
                    $this->session->set_flashdata('flash_message', 'There was a problem updating your password');
                }else{
                    $this->session->set_flashdata('flash_message', 'Your password has been updated. You may now login');
                }
                redirect(site_url().'main/login');                
            }
        }
        
    public function base64url_encode($data) { 
      return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); 
    } 

    public function base64url_decode($data) { 
      return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)); 
    }       
}
