<?php
/* Plugin PhocaGalleryTag Finder
 * copyright 		: Copyright (C) 2023 ConseilGouz. All rights reserved.
 * license    		: http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
*/
namespace Phoca\Plugin\Finder\PhocaGalleryTag\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseQuery;
use Joomla\Registry\Registry;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Event\DispatcherInterface;
defined('JPATH_BASE') or die;

final class PhocaGalleryTag extends Adapter
{
    use DatabaseAwareTrait;
	
	protected $context 			= 'PhocagalleryTag';
	protected $extension 		= 'com_phocagallery';
	protected $layout 			= 'category';
	protected $type_title 		= 'Phoca Gallery Images';
	protected $table 			= '#__phocagallery';
	protected $state_field      = 'published';
	protected $autoloadLanguage = true;
    protected $catid            = 0;
     /**
     * Constructor.
     *
     * @param   DispatcherInterface  $dispatcher  The dispatcher
     * @param   array                $config      An optional associative array of configuration settings
     *
     * @since   4.2.0
     */
    public function __construct(DispatcherInterface $dispatcher, array $config) {
        parent::__construct($dispatcher, $config);
    }

	public function onFinderCategoryChangeState($extension, $pks, $value)
	{
		if ($extension == 'com_phocagallery')
		{
			$this->categoryStateChange($pks, $value);
		}
	}

	public function onFinderAfterDelete($context, $table): void
	{
		if ( $context == 'com_phocagallery.phocagallerytag' || $context == 'com_phocagallery.phocagalleryimg' )	{
			$id = $table->id;
		}
		elseif ($context == 'com_finder.index')	{
			$id = $table->link_id;
		}
		else {
			return;
		}
		// Remove the items.
		$this->remove($id);
	}
	public function onFinderAfterSave($context, $row, $isNew): void
	{
		// We only want to handle web links here. We need to handle front end and back end editing.
		if ($context == 'com_phocagallery.phocagallerytag' || $context == 'com_phocagallery.tag' 
		||  $context == 'com_phocagallery.phocagalleryimg' || $context == 'com_phocagallery.img' ) {
			// Reindex the item
			$this->reindex($row->id);
		}
	}
	public function onFinderBeforeSave($context, $row, $isNew)
	{
		// Check for access levels from the category
		if ($context == 'com_phocagallery.phocagallerycat') {
			// Query the database for the old access level if the item isn't new
			if (!$isNew) {
				$this->checkCategoryAccess($row);
			}
		}
		return true;
	}

	public function onFinderChangeState($context, $pks, $value)
	{
		// We only want to handle web links here
		if ($context == 'com_phocagallery.phocagallerytag' || $context == 'com_phocagallery.tag' 
		||	$context == 'com_phocagallery.phocagalleryimg' || $context == 'com_phocagallery.img' ) {
			$this->itemStateChange($pks, $value);
		}
		// Handle when the plugin is disabled
		if ($context == 'com_plugins.plugin' && $value === 0) {
			$this->pluginDisable($pks);
		}
	}


	protected function index(Result $item)
	{
		// Check if the extension is enabled
		if (ComponentHelper::isEnabled($this->extension) == false) {
			return;
		}

        if (!ComponentHelper::isEnabled('com_phocagallery', true)) {
            echo '<div class="alert alert-danger">Phoca Gallery Error: Phoca Gallery component is not installed or not published on your system</div>';
            return;
        }
        
        $item->setLanguage();
		// Initialize the item parameters.
		$registry = new Registry;
		if (isset($item->params)) {
			$registry->loadString($item->params);
		}
		$item->params = $registry;
		$registry = new Registry;
		if (isset($item->metadata)) {
			$registry->loadString($item->metadata);
		}
		$item->metadata = $registry;
		$this->catid = $item->catid;
		// Build the necessary route and path information.
		$item->url = $this->getURL($item->id,$this->extension, $this->layout);
		$item->route = 'index.php?option=com_phocagallery&view=category&id='.$item->catid.'&tagid='.$item->id;

		// Add the meta-author.
		$item->metaauthor = $item->metadata->get('author');

		// Handle the link to the meta-data.
		$item->addInstruction(Indexer::META_CONTEXT, 'link');
		$item->addInstruction(Indexer::META_CONTEXT, 'metakey');
		$item->addInstruction(Indexer::META_CONTEXT, 'metadesc');
		$item->addInstruction(Indexer::META_CONTEXT, 'metaauthor');
		$item->addInstruction(Indexer::META_CONTEXT, 'author');
		$item->addInstruction(Indexer::META_CONTEXT, 'created_by_alias');

		// Add the type taxonomy data.
		$item->addTaxonomy('Type', 'Phoca Gallery Tags');

		// Add the category taxonomy data.
		if (isset($item->category) && $item->category != '') {
            $item->addTaxonomy('Category', $item->category, $item->cat_state, $item->cat_access);
        }

		// Add the language taxonomy data.
		$item->addTaxonomy('Language', $item->language);

		// Get content extras.
		Helper::getContentExtras($item);

		// Index the item.
		$this->indexer->index($item);
	}

	protected function setup()
	{
//	    require_once JPATH_SITE . '/administrator/components/com_phocagallery/libraries/phocagallery/path/route.php';
		return true;
	}

	protected function getListQuery($query = null)
	{
	    $db = $this->getDatabase();
		// Check if we can use the supplied SQL query.
		$query = $query instanceof DatabaseQuery ? $query : $db->getQuery(true)
			->select('a.id, a.link_cat as catid, a.title, a.alias, "" AS link, a.description AS summary')
			->select('"" as metakey, "" as metadesc, "" as metadata, a.language, a.ordering')
			->select('"" AS created_by_alias, "" AS modified, "" AS modified_by')
			//->select('"" AS publish_start_date, "" AS publish_end_date')
			->select('a.published AS state, a.params, 1 as access');

		// Handle the alias CASE WHEN portion of the query
		$case_when_item_alias = ' CASE WHEN ';
		$case_when_item_alias .= $query->charLength('a.alias', '!=', '0');
		$case_when_item_alias .= ' THEN ';
		$a_id = $query->castAsChar('a.id');
		$case_when_item_alias .= $query->concatenate(array($a_id, 'a.alias'), ':');
		$case_when_item_alias .= ' ELSE ';
		$case_when_item_alias .= $a_id.' END as slug';
		$query->select($case_when_item_alias)
			->from('#__phocagallery_tags AS a')
			->where('a.published = 1');// todo
		return $query;
	}
	protected function getStateQuery()
	{
		$query = $this->db->getQuery(true);
		// Item ID
		$query->select('a.id');
		$query->select('a.published AS state, c.published AS cat_state');
		// Item and category access levels
		//$query->select(' a.access, c.access AS cat_access')
		$query->select(' c.access AS cat_access')
			->from($this->table . ' AS a')
			->join('LEFT', '#__phocagallery_categories AS c ON c.id = a.link_cat');

		return $query;
	}
	protected function getURL($id, $extension, $view) {
		return 'index.php?option='.$extension.'&view='.$view.'&id='.$this->catid.'&tagid='.$id;
	}		
}
