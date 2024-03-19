<?php

# Class to create a basic collection gallery
class collectionGallery extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'database'				=> NULL,
			'table'					=> 'articles',
			'div'					=> 'collectiongallery',
			'tabUlClass'			=> 'tabsflat',
			'databaseStrictWhere'	=> true,
			'nativeTypes'			=> true,
			'administrators'		=> true,
			'useEditing'			=> true,
			'extraFields'			=> array (),
			'locations'				=> false,
			'administrators'		=> 'administrators',
			'page404'				=> false,
			'copyrightOwner'		=> false,
			'introductionHtml'		=> false,
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function assign additional actions
	public function actions ()
	{
		# Specify additional actions
		$actions = array (
			'articles' => array (
				'description' => 'List all articles',
				'url' => 'articles/',
				'tab' => 'Articles',
			),
			'article' => array (
				'description' => 'Article: %detail',
				'usetab' => 'articles',
			),
			'image' => array (
				'description' => 'Image: %detail',
				'usetab' => 'articles',
			),
			'copyright' => array (
				'description' => 'Copyright information',
				'usetab' => false,
			),
			'search' => array (
				'description' => 'Search',
				'tab' => 'Search',
				'url' => 'search/',
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			
			-- Administrators
			CREATE TABLE IF NOT EXISTS `administrators` (
			  `username` varchar(255) NOT NULL COMMENT 'Username' PRIMARY KEY,
			  `active` enum('','Yes','No') NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  `privilege` enum('Administrator','Restricted administrator') NOT NULL DEFAULT 'Administrator' COMMENT 'Administrator level'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='System administrators';
			
			-- Articles
			CREATE TABLE `articles` (
			  `id` varchar(191) NOT NULL COMMENT 'Accession code',
			  `name` varchar(255) NOT NULL COMMENT 'Specimen',
			  `locationId` int NOT NULL,
			  `age` varchar(255) DEFAULT NULL COMMENT 'Age',
			  `siteOfOrigin` varchar(255) DEFAULT NULL COMMENT 'Site of origin',
			  `photograph` varchar(255) DEFAULT NULL COMMENT 'Photograph',
			  `suppress` enum('No','Yes') DEFAULT 'No',
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
			
			-- Locations (optional)
			CREATE TABLE `locations` (
			  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique key',
			  `location` varchar(255) NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
		";
	}
	
	
	
	# Welcome screen
	public function home ()
	{
		# Show the introduction
		$html  = $this->settings['introductionHtml'];
		
		# Listings
		$html .= "\n<h2>Complete listing</h2>";
		$html .= "\n<p>You can <a href=\"{$this->baseUrl}/articles/\">list all items in the collection</a>.</p>";
		
		# Search
		$html .= "\n<h2>Search the collection</h2>";
		$html .= "\n<p>Alternatively, you can search the collection:</p>";
		echo $html;
		$this->search ();
	}
	
	
	# Copyright screen
	public function copyright ()
	{
		# Show the introduction
		$html  = "\n" . '<p>All images on this site are &copy; ' . htmlspecialchars ($this->settings['copyrightOwner']) . '.</p>';
		$html .= "\n" . "<p>If you wish to reproduce an item, please kindly <a href=\"{$this->baseUrl}/feedback.html\">contact us</a> in the first instance.</p>";
		
		# Return the HTML
		echo $html;
	}
	
	
	# Articles listing screen
	public function articles ($restrictionSql = '', $preparedStatementValues = array (), $displayAsList = true)
	{
		# Get the data or end
		if (!$data = $this->getData ($articleId = false, $restrictionSql, $preparedStatementValues)) {
			echo $html = '<p>No articles were found.</p>';
			return false;
		}
		
		# Assemble the HTML, either as a list or table format
		$html = '';
		if ($displayAsList) {
			$this->search ();
			$html .= '<br />';
			$html .= '<p class="comment"><em>The symbol &diams; indicates that a photo is available.</em></p>';
			foreach ($data as $article) {
				$list[] = "<a href=\"{$this->baseUrl}/articles/" . strtolower ($article['id']) . '/' . "\"><strong>" . htmlspecialchars ($article['name']) . "</strong>" . (isSet ($article['siteOfOrigin']) && $article['siteOfOrigin'] ? " ({$article['siteOfOrigin']})" : '') . '</a>' . (isSet ($article['age']) && $article['age'] ? " ({$article['age']})" : '') . ($article['photograph'] ? ' &diams;' : '');
			}
			$html .= application::htmlUl ($list, 0, 'compact small');
			
		# Or use table format
		} else {
			$headings = $this->databaseConnection->getHeadings ($this->settings['database'], $this->settings['table']);
			foreach ($data as $article) {
				$html .= "\n<h3 class=\"listing\"><a href=\"{$this->baseUrl}/articles/" . strtolower ($article['id']) . '/' . "\">" . htmlspecialchars ($article['name']) . '</a>:</h3>';
				unset ($article['name']);
				foreach ($article as $key => $value) {
					$article[$key] = htmlspecialchars ($value);
				}
				if ($article['photograph']) {$article['photograph'] = '&diams; Photo available';}
				$html .= "\n" . application::htmlTableKeyed ($article, $headings, $omitEmpty = true, $class = 'lines', $allowHtml = true);
			}
		}
		
		# Echo the HTML
		echo $html;
	}
	
	
	# Article page
	public function article ($articleId)
	{
		# Ensure the article number is syntactically valid, if supplied
		if (!preg_match ('/^([a-z0-9]+)$/', $articleId)) {
			$this->page404 ();
			return false;
		}
		
		# Get the article data or end
		if (!$data = $this->getData ($articleId)) {
			$this->page404 ();
			return false;
		}
		
		# Edit link
		if ($this->userIsAdministrator) {
			echo "<p class=\"actions right\"><a href=\"{$this->baseUrl}/data/{$this->settings['table']}/{$data['id']}/edit.html\"><img src=\"/images/icons/pencil.png\" /> Edit</a></p>";
		}
		
		# Get the field name conversions
		$headings = $this->databaseConnection->getHeadings ($this->settings['database'], $this->settings['table']);
		
		# Cache the image HTML
		$imageHtml = $this->createImageHtmlWrapper ($data);
		
		# Remove data that should not be visible
		unset ($data['photograph']);
		if (isSet ($data['location'])) {unset ($data['location']);}
		
		# Continue with the article
		$html  = "\n" . '<h2>' . str_replace ('%detail', htmlspecialchars ($data['name']), $this->actions[$this->action]['description']) . "</h2>";
		$html .= "\n" . '<div class="article">';
		$html .= "\n" . application::htmlTableKeyed ($data, $headings, '<em class="comment">(Unknown)</em>', 'lines', $allowHtml = true);
		$html .= "\n" . '</div>';
		$html .= "\n\n<div class=\"imagelarge\">" . $imageHtml . "\n</div>";
		
		# Return the HTML
		echo $html;
	}
	
	
	# Wrapper function for createImageHtml (to avoid having to change it much)
	private function createImageHtmlWrapper ($data, $size = 600)
	{
		# Create the image with the required settings
		$this->listingThumbnailSize = 600;
		$copyrightNotice = "\n" . '<p class="imagecopyright">Note: This image below is <a href="' . $this->baseUrl . '/copyright.html">copyright</a> of ' . htmlspecialchars ($this->settings['copyrightOwner']) . ' and may not be reproduced without permission.</p>' . "\n";
		$imageHtml = $this->createImageHtml ($data['photograph'], $this->listingThumbnailSize, 'imagelarge', htmlspecialchars ($data['name']), $copyrightNotice);
		
		# Return the HTML
		return $imageHtml;
	}
	
	
	# Function to get data for one or more articles
	private function getData ($articleId = false, $restrictionSql = '', $preparedStatementValues = array ())
	{
		# Determine the constraints
		$constraints = array ();
		$constraints[] = "suppress != 'Yes'";
		$constraints[] = "name != ''";
		if ($articleId) {
			$constraints[] = "{$this->settings['table']}.id = :id";
			$preparedStatementValues['id'] = $articleId;
		}
		if ($restrictionSql) {
			$constraints[] = $restrictionSql;
		}
		
		# Construct the query
		$query = "SELECT
				{$this->settings['table']}.id,
				name,
				" . ($this->settings['extraFields'] ? implode (', ', $this->settings['extraFields']) . ',' : '') . '
				photograph
				' . ($this->settings['locations'] ? ", locations.location" : '') . "
			FROM {$this->dataSource}"
			. ($this->settings['locations'] ? "	LEFT OUTER JOIN locations ON locationId = locations.id" : '')
			. " WHERE " . implode (' AND ', $constraints) . "
			ORDER BY name
		;";
		
		# Get the data
		$method = ($articleId ? 'getOne' : 'getData');
		if (!$data = $this->databaseConnection->$method ($query, false, true, $preparedStatementValues)) {
			//application::dumpData ($this->databaseConnection->error ());
			return false;
		}
		
		# Return the data
		return $data;
	}
	
	
	# Function to generate thumbnails
	private function createImageHtml ($location, $size = 300, $class = 'right', $alt = 'Image', $copyrightNotice = false)
	{
		# Absence message
		$absenceMessage = '<p class="nullimage">No image available</p>';
		
		# If no image is specified, end
		if (!$location) {return $absenceMessage;}
		
		# Map the image supplied to the disk location
		$imageMapping = array ('.JPG' => '.jpg', '.TIF' => '.tif', '.TIFF' => '.tif',);
		$location = trim (str_replace (array_keys ($imageMapping), array_values ($imageMapping), $location));
		
		# Set the output format; NB GIF intended for thumbnails; JPG is too large filesize and PNG is even worse
		$outputFormat = 'jpg';
		
		# Determine the thumbnail location
		$thumbnailLocation = $this->baseUrl . '/images/' . ($size == $this->listingThumbnailSize ? 'thumbnails/' : '') . str_replace ('.tif', ".{$outputFormat}", $location);
		
		# Get the location of the thumbnail
		$originalFile = $this->imageStoreRoot . $location;
		$thumbnailFile = $_SERVER['DOCUMENT_ROOT'] . $thumbnailLocation;
		
		# Determine if the thumbnail is old
		$thumbnailIsOld = (is_readable ($originalFile) && is_readable ($thumbnailFile) && (filemtime ($originalFile) > filemtime ($thumbnailFile)));
		
		# Create the thumbnail if it doesn't exist or is old
		if (is_readable ($thumbnailFile) && !$thumbnailIsOld) {
			list ($width, $height, $imageType, $imageAttributes) = getimagesize ($thumbnailFile);
		} else {
			
			# Ensure the file exists and is readable, or inform the administrator
			if (!is_readable ($originalFile)) {
				return $absenceMessage;
				#!# Inform the administrator, but compile the message into a single e-mail first
			}
			
			# Obtain the image height and width when scaled
			list ($width, $height, $imageType, $imageAttributes) = getimagesize ($originalFile);
			list ($width, $height) = image::scaledImageDimensions ($width, $height, $size);
			
			# Determine whether to include the watermark if not in tiny-thumbnail mode
			$watermark = ($size != $this->listingThumbnailSize ? 'watermarkImagick' : false);
			
			# Resize the image
			ini_set ('max_execution_time', 300);
			image::resize ($originalFile, $outputFormat, $width, '', $thumbnailFile, $watermark);
		}
		
		# Add a class for portraits
		if ($height > $width) {$class = ($class ? "{$class} " : '') . 'portrait';}
		
		# Show the thumbnail
		$html = "<img" . ($class ? " class=\"{$class}\"" : '') . " width=\"$width\" height=\"$height\"" . " src=\"/images/general/item.gif\" style=\"background-image: url('" . str_replace ('#', '%23', $thumbnailLocation) . "');\" alt=\"Image\" title=\"{$alt}\" />";
		
		# Add on the copyright notice if required
		if ($copyrightNotice) {$html = $copyrightNotice . $html;}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to provide a search facility
	public function search ()
	{
		# Determine any default
		$default = (isSet ($_GET['item']) ? $_GET['item'] : false);
		
		# Create a new form
		$form = new form (array (
			'displayRestrictions' => false,
			'display' => 'template',
			'displayTemplate' => '{search} {[[PROBLEMS]]} {[[SUBMIT]]}<br />{wildcard}',
			'requiredFieldIndicator' => false,
			'submitButtonText' => 'Search!',
			'formCompleteText' => false,
			'reappear' => true,
			'submitTo' => "{$this->baseUrl}/search/",
		));
		$form->input (array (
			'name'		=> 'search',
			'title'		=> 'Search',
			'required'	=> true,
			'default' 	=> $default,
		));
		$form->checkboxes (array (
			'name'		=> 'wildcard',
			'title'		=> 'Allow partial name searching',
			'values'	=> array ('wildcard' => 'Allow partial name searching'),
			'default'	=> array ('wildcard'),
		));
		if (!$result = $form->process ()) {return false;}
		
		# Define the search sub-SQL
		$searchSql = ($result['wildcard']['wildcard'] ? '%' . $result['search'] . '%' : $result['search']);
		
		# Define the restriction, surrounding the search term with a word-boundary limitation
		$restrictions = array ();
		$preparedStatementValues = array ();
		$restrictions[] = "{$this->settings['table']}.id = :id";
		$preparedStatementValues['id'] = $result['search'];
		$restrictions[] = "name LIKE :name";
		$preparedStatementValues['name'] = $searchSql;
		if ($this->settings['extraFields']) {
			foreach ($this->settings['extraFields'] as $extraField) {
				$restrictions[] = "`{$extraField}` LIKE :{$extraField}";
				$preparedStatementValues[$extraField] = $searchSql;
			}
		}
		$restrictionSql = '(' . implode (' OR ', $restrictions) . ')';
		
		# Get the HTML
		$html = $this->articles ($restrictionSql, $preparedStatementValues, $displayAsList = false);
		
		# Echo the HTML
		echo $html;
	}
	
	
	# Admin editing section, substantially delegated to the sinenomine editing component
	public function editing ($attributes = array (), $deny = false, $sinenomineExtraSettings = array ())
	{
		# Define sinenomine settings
		$sinenomineExtraSettings = array (
			'simpleJoin' => true,
		);
		
		# Set attributes
		$attributes = array (
			array ($this->settings['database'], $this->settings['table'], 'accessionCode', array ('regexp' => '^[A-Z][0-9]+$')),
			array ($this->settings['database'], $this->settings['table'], 'photograph', array ('directory' => $_SERVER['DOCUMENT_ROOT'] . $this->baseUrl . '/images/', 'forcedFileName' => '%id', 'lowercaseExtension' => true, 'allowedExtensions' => array ('jpg'))),
		);
		
		# Hand off to the default editor, which will echo the HTML
		parent::editing ($attributes, $deny = false /* i.e. administrators table */, $sinenomineExtraSettings);
	}
}

?>