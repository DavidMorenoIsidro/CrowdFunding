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

class CrowdFundingModelTransaction extends JModelAdmin {
    
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
    public function getTable($type = 'Transaction', $prefix = 'CrowdFundingTable', $config = array()){
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
        $form = $this->loadForm($this->option.'.transaction', 'transaction', array('control' => 'jform', 'load_data' => $loadData));
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
        $data = JFactory::getApplication()->getUserState($this->option.'.edit.transaction.data', array());
        if(empty($data)){
            $data = $this->getItem();
        }
        return $data;
    }
    
    /**
     * Save data into the DB
     * @param $data   The data of item
     * @return     	  Item ID
     */
    public function save($data){
        
        $statusEdited     = false;
        
        $id               = JArrayHelper::getValue($data, "id");
        $txnAmount        = JArrayHelper::getValue($data, "txn_amount");
        $txnCurrency      = JArrayHelper::getValue($data, "txn_currency");
        $txnStatus        = JArrayHelper::getValue($data, "txn_status");
        $txnId            = JArrayHelper::getValue($data, "txn_id");
        $serviceProvider  = JArrayHelper::getValue($data, "service_provider");
        $investorId       = JArrayHelper::getValue($data, "investor_id");
        
        // Load a record from the database.
        $row = $this->getTable();
        $row->load($id);
        
        // Check for changed transaction status.
        $oldState = $row->txn_status;
        $newState = $txnStatus;
        if((strcmp($oldState, $newState) != 0)) {
            
            // Include the content plugins for the on save events.
            JPluginHelper::importPlugin('crowdfundingpayment');
        
            // Trigger the onTransactionChangeStatus event.
            $dispatcher = JEventDispatcher::getInstance();
            $dispatcher->trigger("onTransactionChangeStatus", array($this->option . '.' . $this->name, $row, $oldState, $newState));
            
        }
        
        // Store the transaction data.
        $row->set("txn_amount",        $txnAmount);
        $row->set("txn_currency",      $txnCurrency);
        $row->set("txn_status",        $txnStatus);
        $row->set("txn_id",            $txnId);
        $row->set("service_provider",  $serviceProvider);
        $row->set("investor_id",       $investorId);
        
        $row->store();
        
        return $row->id;
    
    }
    
}