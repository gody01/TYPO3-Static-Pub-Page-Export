<?php
/***************************************************************
 * Copyright notice
 *
 * (c) 2011 AOE media GmbH <dev@aoemedia.de>
 * All rights reserved
 *
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

require_once(PATH_tx_staticpub_pageexport . 'Classes/Domain/Model/File.php');

/**
 * This class crawl a page and put the generated resources in file-repository
 * 
 * @package staticpub_pageexport
 */
class Tx_StaticpubPageexport_System_PageExport {
	/**
	 * @var tx_crawler_lib
	 */
	private $crawlerObj;
	/**
	 * @var Tx_StaticpubPageexport_Domain_Repository_FileRepository
	 */
	private $fileRepository;
	/**
	 * @var integer
	 */
	private $pageId;
	/**
	 * @var tx_staticpub
	 */
	private $staticpubObj;

	/**
	 * crawl page and put HTML-file (and resources like images) of crawled page into file-repository
	 * 
	 * @param $pageId
	 * @return Tx_StaticpubPageexport_System_PageExport
	 * @return getFileRepository
	 */
	public function exportPage($pageId) {
		$this->setPageId( $pageId );
		$this->step1_setCrawlerInstruction();
		$this->step2_doCrawling();
		$this->step3_addResourcesOfCrawledPageToFileRepository();
		return $this->getFileRepository();
	}

	/**
	 * @param array $data
	 */
	protected function addFilesToFileRepository(array $data) {
		// add HTML-file
		$path = $this->getPublishDirForPage($data);
		$this->getFileRepository()->addFile( $this->createFile('index.html', $path, '') );

		// add resources (images etc.)
		if(NULL !== $allResources = $this->getArrayElement($data, 'log|resources')) {
			$publishDirForResources = $this->getPublishDirForResources( $data );
			foreach($allResources as $resources) {
				foreach($resources as $resource) {
					$name = $resource['filename'];
					$relativePath = preg_replace('/^\//', '', $resource['path']);
					$originalPath = $publishDirForResources . $relativePath;
					$this->getFileRepository()->addFile( $this->createFile($name, $originalPath, $relativePath) );
				}
			}
		}

		// add pageId
		$this->getFileRepository()->setPageId( $this->getPageId() );
	}

	/**
	 * @param string $name
	 * @param string $originalPath
	 * @param string $relativePath
	 * @return Tx_StaticpubPageexport_Domain_Model_File
	 */
	protected function createFile($name, $originalPath, $relativePath) {
		return new Tx_StaticpubPageexport_Domain_Model_File($name, $originalPath, $relativePath);
	}

	/**
	 * @return tx_crawler_lib
	 */
	protected function getCrawler() {
		if(!isset($this->crawlerObj)) {
			$this->crawlerObj = t3lib_div::makeInstance('tx_crawler_lib');
			$this->crawlerObj->setID = t3lib_div::md5int(microtime());
		}
		return $this->crawlerObj;
	}
	/**
	 * @return Tx_StaticpubPageexport_Domain_Repository_FileRepository
	 */
	protected function getFileRepository() {
		if(!isset($this->fileRepository)) {
			$this->fileRepository = t3lib_div::makeInstance('Tx_StaticpubPageexport_Domain_Repository_FileRepository');
		}
		return $this->fileRepository;
	}
	/**
	 * @param array $data
	 * @return string
	 */
	protected function getPublishDirForPage(array $data) {
		$publishDirForPage = '';
		$publishDir = $this->getArrayElement($data, 'log|tx_staticpub_publishdir');
		$path = $this->getArrayElement($data, 'log|tx_staticpub_path');
		if($publishDir !== NULL && $path !== NULL) {
			$publishDirForPage = PATH_site . $publishDir . $path;
		}
		return $publishDirForPage;
	}
	/**
	 * @param array $data
	 * @return string
	 */
	protected function getPublishDirForResources(array $data) {
		$publishDirForResources = '';
		if(NULL !== $publishDirForResources = $this->getArrayElement($data, 'parameters|procInstrParams|tx_staticpub_publish.|publishDirForResources')) {
			$publishDirForResources = PATH_site . $publishDirForResources;
		} elseif(NULL !== $publishDir = $this->getArrayElement($data, 'log|tx_staticpub_publishdir')) {
			$publishDirForResources = PATH_site . $publishDir;
		}
		return $publishDirForResources;
	}
	/**
	 * @return integer
	 */
	protected function getPageId() {
		return $this->pageId;
	}
	/**
	 * @return tx_staticpub
	 */
	protected function getStaticpubObj() {
		if(!isset($this->staticpubObj)) {
			require_once( t3lib_extMgm::extPath('staticpub') . 'class.tx_staticpub.php');
			$this->staticpubObj = t3lib_div::makeInstance('tx_staticpub');
		}
		return $this->staticpubObj;
	}

	/**
	 * @param array $array
	 * @param string $pathToElement
	 * @return mixed
	 */
	private function getArrayElement($array, $pathToElement) {
		$path = explode( '|', $pathToElement );
		$element = $array;
		foreach($path as $level) {
			if(is_array($element) && array_key_exists($level, $element)) {
				$element = $element[$level];
			} else {
				break;
				$element = NULL;
			}
		}
		return $element;
	}
	/**
	 * @param integer $pageId
	 * @return Tx_StaticpubPageexport_System_PageExport
	 */
	private function setPageId($pageId) {
		$this->pageId = $pageId;
	}
	/**
	 * set crawler-instruction (this needs the crawler to crawl the page)
	 */
	private function step1_setCrawlerInstruction() {
		$this->getCrawler()->getPageTreeAndUrls (
			$this->getPageId(),
			0,
			time(),
			10000,
			true,
			false,
			array('tx_staticpub_publish'),
			array() 
		);
	}
	/**
	 * do crawling (processing crawler-instruction)
	 */
	private function step2_doCrawling() {
		$this->getCrawler()->CLI_main();
	}
	/**
	 * add resources of crawled page to file-repository
	 */
	private function step3_addResourcesOfCrawledPageToFileRepository() {
		$entries = $this->getCrawler()->getLogEntriesForSetId($this->getCrawler()->setID, 'finished');
		foreach($entries as $entry) {
			if((integer) $entry['page_id'] === $this->getPageId()) {
				$resultData = unserialize( $entry['result_data'] );
				$contentData = unserialize( $resultData['content'] );

				if($this->getArrayElement($contentData, 'success|tx_staticpub') === TRUE) {
					$this->addFilesToFileRepository($contentData);
					break;
				}
			}
		}
	}
}