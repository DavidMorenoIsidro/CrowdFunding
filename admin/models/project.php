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

jimport('joomla.application.component.modeladmin');

class CrowdFundingModelProject extends JModelAdmin {
    
    /**
     * @var     string  The prefix to use with controller messages.
     * @since   1.6
     */
    protected $text_prefix = 'COM_CROWDFUNDING';
    
    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   type    The table type to instantiate
     * @param   string  A prefix for the table class name. Optional.
     * @param   array   Configuration array for model. Optional.
     * @return  JTable  A database object
     * @since   1.6
     */
    public function getTable($type = 'Project', $prefix = 'CrowdFundingTable', $config = array()){
        return JTable::getInstance($type, $prefix, $config);
    }
    
    /**
     * Method to get the record form.
     *
     * @param   array   $data       An optional array of data for the form to interogate.
     * @param   boolean $loadData   True if the form is to load its own data (default case), false if not.
     * @return  JForm   A JForm object on success, false on failure
     * @since   1.6
     */
    public function getForm($data = array(), $loadData = true){
        
        // Get the form.
        $form = $this->loadForm($this->option.'.project', 'project', array('control' => 'jform', 'load_data' => $loadData));
        if(empty($form)){
            return false;
        }
        
        return $form;
    }
    
    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed   The data for the form.
     * @since   1.6
     */
    protected function loadFormData(){
        // Check the session for previously entered form data.
        $data = JFactory::getApplication()->getUserState($this->option.'.edit.project.data', array());
        if(empty($data)){
            $data = $this->getItem();
        }
        
        return $data;
    }
    
    /**
     * Save data into the DB
     * 
     * @param $data   The data about item
     * @return     Item ID
     */
    public function save($data){
        
        $id           = JArrayHelper::getValue($data, "id");
        $title        = JArrayHelper::getValue($data, "title");
        $alias        = JArrayHelper::getValue($data, "alias");
        $goal         = JArrayHelper::getValue($data, "goal");
        $funded       = JArrayHelper::getValue($data, "funded");
        $fundingType  = JArrayHelper::getValue($data, "funding_type");
        $pitchVideo   = JArrayHelper::getValue($data, "pitch_video");
        $shortDesc    = JArrayHelper::getValue($data, "short_desc");
        $description  = JArrayHelper::getValue($data, "description");
        $catId        = JArrayHelper::getValue($data, "catid");
        $published    = JArrayHelper::getValue($data, "published");
        $approved     = JArrayHelper::getValue($data, "approved");
        
        // Load a record from the database
        $row = $this->getTable();
        $row->load($id);
        
        $row->set("title",          $title);
        $row->set("alias",          $alias);
        $row->set("goal",           $goal);
        $row->set("funded",         $funded);
        $row->set("funding_type",   $fundingType);
        $row->set("pitch_video",    $pitchVideo);
        $row->set("catid",          $catId);
        $row->set("published",      $published);
        $row->set("approved",       $approved);
        $row->set("short_desc",     $shortDesc);
        $row->set("description",    $description);
        
        $row->store();
        
        return $row->id;
    
    }
    
	/**
	 * Method to change the approved state of one or more records.
	 *
	 * @param   array    A list of the primary keys to change.
	 * @param   integer  The value of the approved state.
	 */
	public function approve(array $pks, $value) {
	    
	    $table      = $this->getTable();
	    $pks        = (array)$pks;
	     
		$db      = JFactory::getDbo();
		
		$query   = $db->getQuery(true);
		$query
		    ->update($db->quoteName("#__crowdf_projects"))
		    ->set("approved = " . (int)$value)
		    ->where("id IN (".implode(",", $pks).")");

	    $db->setQuery($query);
	    $db->query();
	    
	    // Trigger change state event
	    
	    $context = $this->option . '.' . $this->name;
	     
	    // Include the content plugins for the change of state event.
	    JPluginHelper::importPlugin('content');
	     
	    // Trigger the onContentChangeState event.
	    $dispatcher = JEventDispatcher::getInstance();
	    $result     = $dispatcher->trigger($this->event_change_state, array($context, $pks, $value));
	    
	    if (in_array(false, $result, true)) {
	        throw new Exception(JText::_("COM_CROWDFUNDING_ERROR_CHANGE_STATE"), ITPrismErrors::CODE_WARNING);
	    }
	    
		// Clear the component's cache
		$this->cleanCache();

	}
	
	/**
	 * Method to toggle the featured setting of articles.
	 *
	 * @param   array    The ids of the items to toggle.
	 * @param   integer  The value to toggle to.
	 *
	 * @return  boolean  True on success.
	 */
	public function featured(array $pks, $value = 0) {
	    
		$db      = JFactory::getDbo();
		
		$query   = $db->getQuery(true);
		$query
		    ->update($db->quoteName("#__crowdf_projects"))
		    ->set("featured = " . (int)$value)
		    ->where("id IN (".implode(",", $pks).")");

	    $db->setQuery($query);
	    $db->query();
	    
		// Clear the component's cache
		$this->cleanCache();

	}
	
	/**
	 * Method to change the published state of one or more records.
	 *
	 * @param   array    &$pks   A list of the primary keys to change.
	 * @param   integer  $value  The value of the published state.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   12.2
	 */
	public function publish(&$pks, $value = 0) {
	    
	    $table      = $this->getTable();
	    $pks        = (array) $pks;
	    
	    // Access checks.
	    foreach ($pks as $i => $pk) {
	        
	        $table->reset();
	
	        if ($table->load($pk)) {
	            
	            if($value == CrowdFundingConstants::PUBLISHED) { // Publish a project

	                // Validate funding period
	                if(!$table->funding_days AND !CrowdFundingHelper::isValidDate($table->funding_end)) {
	                    throw new Exception(JText::_("COM_CROWDFUNDING_ERROR_INVALID_DURATION_PERIOD"), ITPrismErrors::CODE_WARNING);
	                }
	                
	                
	                // Calculate starting date if the user publish a project for first time.
	                if(!CrowdFundingHelper::isValidDate($table->funding_start)) {
	                    $fundindStart         = new JDate();
	                    $table->funding_start = $fundindStart->toSql();
	                    
	                    // If funding type is "days", calculate end date.
	                    if(!empty($table->funding_days)) {
	                        $table->funding_end = CrowdFundingHelper::calcualteEndDate($table->funding_start, $table->funding_days);
	                    }
	                }
	                
	                // Validate the period if the funding type is days
	                $params    = JComponentHelper::getParams($this->option);
	                
	                $minDays   = $params->get("project_days_minimum", 15);
	                $maxDays   = $params->get("project_days_maximum");
	                
	                if(CrowdFundingHelper::isValidDate($table->funding_end)) {
	                    
	                    if(!CrowdFundingHelper::isValidPeriod($table->funding_start, $table->funding_end, $minDays, $maxDays)) {
	                        if(!empty($maxDays)) {
	                            throw new Exception(JText::sprintf("COM_CROWDFUNDING_ERROR_INVALID_ENDING_DATE_MIN_MAX_DAYS", $minDays, $maxDays), ITPrismErrors::CODE_WARNING);
	                        } else {
	                            throw new Exception(JText::sprintf("COM_CROWDFUNDING_ERROR_INVALID_ENDING_DATE_MIN_DAYS", $minDays), ITPrismErrors::CODE_WARNING);
	                        }
	                    }
	                
	                }
	                
	                $table->published = CrowdFundingConstants::PUBLISHED;
	                $table->store();
	                
	            } else { // Set other states - unpublished, trash,...
	                $table->publish(null, $value);
	            }
	        }
	    }
	
	    
	    // Trigger change state event
	    
	    $context = $this->option . '.' . $this->name;
	    
	    // Include the content plugins for the change of state event.
	    JPluginHelper::importPlugin('content');
	    
	    // Trigger the onContentChangeState event.
	    $dispatcher = JEventDispatcher::getInstance();
	    $result     = $dispatcher->trigger($this->event_change_state, array($context, $pks, $value));
	
	    if (in_array(false, $result, true)) {
	        throw new Exception(JText::_("COM_CROWDFUNDING_ERROR_CHANGE_STATE"), ITPrismErrors::CODE_WARNING);
	    }
	
	    // Clear the component's cache
	    $this->cleanCache();
	
	}
	
	/**
	 * A protected method to get a set of ordering conditions.
	 *
	 * @param	object	A record object.
	 *
	 * @return	array	An array of conditions to add to add to ordering queries.
	 * @since	1.6
	 */
	protected function getReorderConditions($table) {
	    $condition   = array();
	    $condition[] = 'catid = '.(int) $table->catid;
	    return $condition;
	}
}