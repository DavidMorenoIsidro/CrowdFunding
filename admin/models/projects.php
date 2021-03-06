<?php
/**
 * @package      CrowdFunding
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2013 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.modellist' );

class CrowdFundingModelProjects extends JModelList {
    
	 /**
     * Constructor.
     *
     * @param   array   An optional associative array of configuration settings.
     * @see     JController
     * @since   1.6
     */
    public function  __construct($config = array()) {
        
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'title', 'a.title',
                'goal', 'a.goal',
                'funded', 'a.funded',
                'funded_percents',
            	'funding_start', 'a.funding_start',
            	'funding_end', 'a.funding_end',
            	'ordering', 'a.ordering',
                'published', 'a.published',
                'approved', 'a.approved',
            	'created', 'a.created',
                'category', 'b.title',
            );
        }

        parent::__construct($config);
		
    }
    
    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @since   1.6
     */
    protected function populateState($ordering = null, $direction = null) {
        
        // Load the component parameters.
        $params = JComponentHelper::getParams($this->option);
        $this->setState('params', $params);
        
        // Load filter search.
        $value = $this->getUserStateFromRequest($this->context.'.filter.search', 'filter_search');
        $this->setState('filter.search', $value);

        // Load filter state.
        $value = $this->getUserStateFromRequest($this->context.'.filter.state', 'filter_state', '', 'string');
        $this->setState('filter.state', $value);
        
        // Load filter approved state.
        $value = $this->getUserStateFromRequest($this->context.'.filter.approved', 'filter_approved', '', 'string');
        $this->setState('filter.approved', $value);
        
        // Load filter featured state.
        $value = $this->getUserStateFromRequest($this->context.'.filter.featured', 'filter_featured', '', 'string');
        $this->setState('filter.featured', $value);
        
        // Load filter category.
        $value = $this->getUserStateFromRequest($this->context.'.filter.category_id', 'filter_category_id', 0, 'int');
        $this->setState('filter.category_id', $value);

        // List state information.
        parent::populateState('a.created', 'asc');
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param   string      $id A prefix for the store id.
     * @return  string      A store id.
     * @since   1.6
     */
    protected function getStoreId($id = '') {
        
        // Compile the store id.
        $id.= ':' . $this->getState('filter.search');
        $id.= ':' . $this->getState('filter.state');
        $id.= ':' . $this->getState('filter.approved');
        $id.= ':' . $this->getState('filter.featured');
        $id.= ':' . $this->getState('filter.category_id');

        return parent::getStoreId($id);
    }
    
   /**
     * Build an SQL query to load the list data.
     *
     * @return  JDatabaseQuery
     * @since   1.6
     */
    protected function getListQuery() {
        
        $db     = $this->getDbo();
        /** @var $db JDatabaseMySQLi **/
        
        // Create a new query object.
        $query  = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                'a.id, a.title, a.goal, a.funded, a.funding_start, a.funding_end, '. 
                'a.funding_days, a.ordering, a.created, a.catid, ROUND( (a.funded/a.goal) * 100, 1 ) AS funded_percents, '.
                'a.featured, a.published, a.approved, '.
                'b.title AS category'
            )
        );
        $query->from($db->quoteName('#__crowdf_projects').' AS a');
        $query->innerJoin($db->quoteName('#__categories').' AS b ON a.catid = b.id');

        // Filter by category
        $categoryId = $this->getState('filter.category_id');
        if (!empty($categoryId)) {
            $query->where('b.id = '.(int) $categoryId);
        }
        
        // Filter by state
        $state = $this->getState('filter.state');
        if (is_numeric($state)) {
            $query->where('a.published = '.(int) $state);
        } else if ($state === '') {
            $query->where('(a.published IN (0, 1))');
        }
        
        // Filter by approved state
        $state = $this->getState('filter.approved');
        if (is_numeric($state)) {
            $query->where('a.approved = '.(int) $state);
        } else if ($state === '') {
            $query->where('(a.approved IN (0, 1))');
        }
        
        // Filter by approved state
        $state = $this->getState('filter.featured');
        if (is_numeric($state)) {
            $query->where('a.featured = '.(int) $state);
        } else if ($state === '') {
            $query->where('(a.featured IN (0, 1))');
        }

        // Filter by search in title
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = '.(int) substr($search, 3));
            } else {
                $escaped = $db->escape($search, true);
                $quoted  = $db->quote("%" . $escaped . "%", false);
                $query->where('a.title LIKE '.$quoted);
            }
        }
        
        // Add the list ordering clause.
        $orderString = $this->getOrderString();
        $query->order($db->escape($orderString));

        return $query;
    }
    
    protected function getOrderString() {
        
        $orderCol   = $this->getState('list.ordering',  'a.created');
        $orderDirn  = $this->getState('list.direction', 'asc');
        if ($orderCol == 'a.ordering') {
            $orderCol = 'a.catid '.$orderDirn.', a.ordering';
        }
        
        return $orderCol.' '.$orderDirn;
    }
    
    /**
     * Count and return rewards number.
     * 
     * @param array $projectsIds
     * @return array
     */
    public function getRewardsNumber(array $projectsIds) {
        
        if(!$projectsIds) {
            return array();
        }
        
        $db     = $this->getDbo();
        /** @var $db JDatabaseMySQLi **/
        
        // Create a new query object.
        $query  = $db->getQuery(true);
        
        $query
            ->select("a.project_id, COUNT(*) as number")
            ->from($db->quoteName("#__crowdf_rewards") . " AS a")
            ->where("a.project_id IN (".implode(",", $projectsIds) .")")
            ->group("a.project_id");
        
        $db->setQuery($query);

        $results = $db->loadObjectList("project_id");
        
        if(!$results) {
            $results=  array();
        }
        return $results;
    }
}