<?php
/**
 * @package      CrowdFunding
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2013 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die;

jimport('itprism.controller.form.frontend');

/**
 * CrowdFunding story controller
 *
 * @package     ITPrism Components
 * @subpackage  CrowdFunding
  */
class CrowdFundingControllerStory extends ITPrismControllerFormFrontend {
    
	/**
     * Method to get a model object, loading it if required.
     *
     * @param	string	$name	The model name. Optional.
     * @param	string	$prefix	The class prefix. Optional.
     * @param	array	$config	Configuration array for model. Optional.
     *
     * @return	object	The model.
     * @since	1.5
     */
    public function getModel($name = 'Story', $prefix = 'CrowdFundingModel', $config = array('ignore_request' => true)) {
        
        JLoader::register("CrowdFundingModelProject", JPATH_COMPONENT.DIRECTORY_SEPARATOR."models".DIRECTORY_SEPARATOR."project.php");
        $model = parent::getModel($name, $prefix, $config);
        
        return $model;
    }
    
    public function save() {
        
        // Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));
 
		$userId = JFactory::getUser()->id;
        if(!$userId) {
            $redirectData = array(
                "force_direction" => "index.php?option=com_users&view=login"
            );
            $this->displayNotice(JText::_('COM_CROWDFUNDING_ERROR_NOT_LOG_IN'), $redirectData);
            return;
        }
        
        $app = JFactory::getApplication();
        /** @var $app JAdministrator **/
        
		// Get the data from the form POST
		$data    = $app->input->post->get('jform', array(), 'array');
        $itemId  = JArrayHelper::getValue($data, "id");
        
        $redirectData = array(
            "view"   => "project",
            "layout" => "story",
            "id"     => $itemId
        );
        
        $model   = $this->getModel();
        /** @var $model CrowdFundingModelStory **/
        
        $form    = $model->getForm($data, false);
        /** @var $form JForm **/
        
        if(!$form){
            throw new Exception($model->getError(), 500);
        }
            
        // Test if the data is valid.
        $validData = $model->validate($form, $data);
        
        // Check for validation errors.
        if($validData === false){
            $this->displayNotice($form->getErrors(), $redirectData);
            return;
        }
        
        try {
            
            // Get image
            $image   = $app->input->files->get('jform', array(), 'array');
            $image   = JArrayHelper::getValue($image, "pitch_image");
            
            // Upload image
            if(!empty($image['name'])) {
            
                jimport('joomla.filesystem.folder');
                jimport('joomla.filesystem.file');
                jimport('joomla.filesystem.path');
                jimport('joomla.image.image');
                jimport('itprism.file.upload.image');
            
                $imageName    = $model->uploadImage($image);
                if(!empty($imageName)) {
                    $validData["pitch_image"] = $imageName;
                }
            
            }
            
            $itemId = $model->save($validData);
            
            $redirectData["id"] = $itemId;
            
        } catch (Exception $e) {
            
            // Problem with uploading, so set a message and redirect to pages
            $code = $e->getCode();
            switch($code) {
                
                case ITPrismErrors::CODE_WARNING:
                    $this->displayWarning($e->getMessage(), $redirectData);
                    return;
                break;
                
                case ITPrismErrors::CODE_HIDDEN_WARNING:
                    $this->displayWarning(JText::_("COM_CROWDFUNDING_ERROR_FILE_CANT_BE_UPLOADED"), $redirectData);
                    return;
                break;
                
                default:
                    JLog::add($e->getMessage());
                    throw new Exception(JText::_('COM_CROWDFUNDING_ERROR_SYSTEM'), ITPrismErrors::CODE_ERROR);
                break;
            }
            
        }
        
        // Validate pitch image and video URL
        $item    = $model->getItem($itemId, $userId);
        if(!$item->pitch_image AND !$item->pitch_video) {
            
            // Redirect to next page
            $redirectData = array(
                "view"   => "project",
                "layout" => "story",
                "id"     => $itemId
            );
            
            $this->displayNotice(JText::_("COM_CROWDFUNDING_ERROR_INVALID_PITCH_IMAGE_OR_VIDEO"), $redirectData);
            return;
        }
        
		// Redirect to next page
        $redirectData = array(
            "view"   => "project",
            "layout" => "rewards",
            "id"     => $itemId
        );
		$this->displayMessage(JText::_("COM_CROWDFUNDING_STORY_SUCCESSFULY_SAVED"), $redirectData);
    }
    
	/**
     * Delete image
     */
    public function removeImage() {
        
        // Check for request forgeries.
        JSession::checkToken("get") or jexit(JText::_('JINVALID_TOKEN'));
        
        $app = JFactory::getApplication();
        /** @var $app JSite **/
        
        // Check for registered user
        $userId  = JFactory::getUser()->id;
        if(!$userId) {
            $redirectData = array(
                "force_direction" => "index.php?option=com_users&view=login"
            );
            $this->displayNotice(JText::_('COM_CROWDFUNDING_ERROR_NOT_LOG_IN'), $redirectData);
            return;
        }
        
        $itemId  = $app->input->get->getInt("id");
        $redirectData = array(
            "view"   => "project",
            "layout" => "story"
        );
        
        // Check for valid item
        if(!$itemId) {
            $this->displayNotice(JText::_('COM_CROWDFUNDING_ERROR_INVALID_IMAGE'), $redirectData);
            return;
        }
        
        try {
            
            $model = $this->getModel();
            $model->removeImage($itemId, $userId);
            
        } catch ( Exception $e ) {
            JLog::add($e->getMessage());
            throw new Exception(JText::_('COM_CROWDFUNDING_ERROR_SYSTEM'));
        }
        
        $redirectData["id"] = $itemId;
        $this->displayMessage(JText::_('COM_CROWDFUNDING_IMAGE_DELETED'), $redirectData);
        
        
    }
    
}