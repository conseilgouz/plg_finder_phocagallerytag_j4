<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseQuery;
use Joomla\CMS\Factory;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;

defined('JPATH_BASE') or die;

class PlgFinderPhocagalleryTag extends Adapter
{
	protected $context 			= 'PhocagalleryTags';
	protected $extension 		= 'com_phocagallery';
	protected $layout 			= 'category';
	protected $type_title 		= 'Phoca Gallery Images';
	protected $table 			= '#__phocagallery';
	protected $autoloadLanguage = true;


	public function onFinderCategoryChangeState($extension, $pks, $value)
	{
		if ($extension == 'com_phocagallery')
		{
			$this->categoryStateChange($pks, $value);
		}
	}

	public function onFinderAfterDelete($context, $table)
	{
		if ($context == 'com_phocagallery.phocagallerytag')
		{
			$id = $table->id;
		}
		elseif ($context == 'com_finder.index')
		{
			$id = $table->link_id;
		}
		else
		{
			return true;
		}
		// Remove the items.
		return $this->remove($id);
	}

	public function onFinderAfterSave($context, $row, $isNew)
	{
		// We only want to handle web links here. We need to handle front end and back end editing.
		if ($context == 'com_phocagallery.phocagallerytag' || $context == 'com_phocagallery.tag' )
		{
			// Check if the access levels are different
			if (!$isNew && $this->old_access != $row->access)
			{
				// Process the change.
				$this->itemAccessChange($row);
			}

			// Reindex the item
			$this->reindex($row->id);
		}
		return true;
	}


	public function onFinderBeforeSave($context, $row, $isNew)
	{
		// We only want to handle web links here
		if ($context == 'com_phocagallery.phocagallerytag' || $context == 'com_phocagallery.tag' )
		{
			// Query the database for the old access level if the item isn't new
			if (!$isNew)
			{
				$this->checkItemAccess($row);
			}
		}

		// Check for access levels from the category
		if ($context == 'com_phocagallery.phocagallerycat')
		{
			// Query the database for the old access level if the item isn't new
			if (!$isNew)
			{
				$this->checkCategoryAccess($row);
			}
		}

		return true;
	}

	public function onFinderChangeState($context, $pks, $value)
	{
		// We only want to handle web links here
		if ($context == 'com_phocagallery.phocagallerytag' || $context == 'com_phocagallery.tag' )
		{
			$this->itemStateChange($pks, $value);
		}
		// Handle when the plugin is disabled
		if ($context == 'com_plugins.plugin' && $value === 0)
		{
			$this->pluginDisable($pks);
		}

	}


	protected function index(FinderIndexerResult $item, $format = 'html')
	{
		// Check if the extension is enabled
		if (JComponentHelper::isEnabled($this->extension) == false)
		{
			return;
		}


        if (!JComponentHelper::isEnabled('com_phocagallery', true)) {
            echo '<div class="alert alert-danger">Phoca Gallery Error: Phoca Gallery component is not installed or not published on your system</div>';
            return;
        }

        if (!class_exists('PhocaGalleryLoader')) {
            require_once( JPATH_ADMINISTRATOR.'/components/com_phocagallery/libraries/loader.php');
        }

        phocagalleryimport('phocagallery.path.path');
        phocagalleryimport('phocagallery.path.route');
        phocagalleryimport('phocagallery.library.library');
        phocagalleryimport('phocagallery.text.text');
        phocagalleryimport('phocagallery.access.access');
        phocagalleryimport('phocagallery.file.file');
        phocagalleryimport('phocagallery.file.filethumbnail');



		$item->setLanguage();

		// Initialize the item parameters.
		$registry = new JRegistry;
		$registry->loadString($item->params);
		$item->params = $registry;

		$registry = new JRegistry;
		$registry->loadString($item->metadata);
		$item->metadata = $registry;

		// Build the necessary route and path information.
		$item->url = 'index.php?option=com_phocagallery&view=category&id='.$item->catid.'&tagid='.$item->id;

		$item->route = 'index.php?option=com_phocagallery&view=category&id='.$item->catid.'&tagid='.$item->id;

		// Add the meta-author.
		$item->metaauthor = $item->metadata->get('author');

		// Handle the link to the meta-data.
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'link');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'metakey');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'metadesc');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'metaauthor');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'author');
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'created_by_alias');

		// Add the type taxonomy data.
		$item->addTaxonomy('Type', 'Phoca Gallery Tags');

		// Add the category taxonomy data.
		if (isset($item->category) && $item->category != '') {
            $item->addTaxonomy('Category', $item->category, $item->cat_state, $item->cat_access);
        }

		// Add the language taxonomy data.
		$item->addTaxonomy('Language', $item->language);

		// Get content extras.
		FinderIndexerHelper::getContentExtras($item);

		// Index the item.
		$this->indexer->index($item);
	}

	protected function setup()
	{
		require_once JPATH_SITE . '/administrator/components/com_phocagallery/libraries/phocagallery/path/route.php';
		return true;
	}

	protected function getListQuery($query = null)
	{
		$db = Factory::getDbo();
		// Check if we can use the supplied SQL query.
		$query = $query instanceof JDatabaseQuery ? $query : $db->getQuery(true)
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

	protected function getUpdateQueryByTime($time)
	{
		// Build an SQL query based on the modified time.
		$query = $this->db->getQuery(true)
			->where('a.date >= ' . $this->db->quote($time));

		return $query;
	}

	protected function getStateQuery()
	{
		$query = $this->db->getQuery(true);

		// Item ID
		$query->select('a.id');

		// Item and category published state
		//$query->select('a.' . $this->state_field . ' AS state, c.published AS cat_state');
		$query->select('a.published AS state, c.published AS cat_state');
		// Item and category access levels
		//$query->select(' a.access, c.access AS cat_access')
		$query->select(' c.access AS cat_access')
			->from($this->table . ' AS a')
			->join('LEFT', '#__phocagallery_categories AS c ON c.id = a.link_cat');

		return $query;
	}
}
