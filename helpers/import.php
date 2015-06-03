<?php
/**
 * VimeoImport
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The VimeoImport import helper class.
 *
 * @package VimeoImport
 */
class VimeoImport_ImportHelper
{
  
  /**
   * @var string Vimeo access key for this app
   */
  public static $vimeo_access_key = 'c69cf13bd30ed59a6057d6c54a1396b8';
  
  /**
   *Parse the Vimeo url parameter 
   *
   *@return $string $setID A unique identifier for the Vimeo collection
   */
  public static function ParseURL($url)
  {
    $parsed = parse_url($url);
    
    if(!empty($parsed['query']))
      {
	parse_str($parsed['query'],$parsed);
	$videoID = $parsed['v'];
      } elseif (isset($parsed['path'])) {
      $videoID = str_replace("/","",$parsed['path']);
      }
    return($videoID);
  }

  private static function _addPlayerElement() {
      if(element_exists(ElementSet::ITEM_TYPE_NAME,'Player'))
          return;

      $db = get_db();
      $table = $db->getTable('ItemType');
      $mpType = $table->findByName('Moving Image');
      if(!is_object($mpType)) {
          $mpType = new ItemType();
          $mpType->name = "Moving Image";
          $mpType->description = "A series of visual representations imparting an impression of motion when shown in succession. Examples include animations, movies, television programs, videos, zoetropes, or visual output from a simulation.";
          $mpType->save();
      }
      $mpType->addElements(array(
          array(
              'name'=>'Player',
              'description'=>'html for embedded player to stream video content'
          )
      ));
      $mpType->save();
  }
  
  /**
   *Fetch metadata from a Vimeo video and prepare it
   *
   *@param string $itemID The Vimeo video ID from which to extract metadata
   *@param object $service The vimeo API php interface instance
   *@param int $collection The ID of the collection to which to add the new item
   *@param string $ownerRole The name of the dublin core field to which to 
   *add the Vimeo user info
   *@param boolean public Indicates whether the new omeka item should be public
   *@return array An array containing metadata associated with the 
   *given vimeo video in the correct format to save as an omeka item,
   *and urls of files associated
   */
  public static function GetVideo($itemID,$service,$collection=0,$ownerRole='Publisher',$public=0)
  {

    $part = "id,snippet,contentDetails,player,status,recordingDetails";
    
    $response = $service->videos->listVideos($part, array(
							  'id'=>$itemID,
							  'maxResults'=>1
							  ));
    
    if (empty($response)) 
      throw new Exception("No video found."); 

    $items = $response->items;

    if (empty($items)) 
     throw new Exception('No video found for itemID '.$itemID);

    $video = $items[0];

    //todo format date if necessary
    $datePublished = $video['snippet']['publishedAt'];

    try{
      $recordingDetails = $video['recordingDetails'];
    } catch (Exception $e) {
      die('exception');
      $recordingDetails = array();
    }

    //recordingDetails are only returned for authenticated requests, apparently!
    //or maybe users can hide them from the public.

    $dateRecorded = "";
    if(!empty($recordingDetails['RecordingDate']))
      $dateRecorded = $recordingDetails['RecordingDate'];

    $spatialCoverage = "";
    if(!empty($recordingDetails['locationDescription']))
       $spatialCoverage .= $recordingDetails['locationDescription']."<br>";
    if(!empty($recordingDetails['locationDescription']))
       $spatialCoverage .= $recordingDetails['locationDescription']."<br>";
    
    if(!empty($recordingDetails['location']))
      foreach($recordingDetails['location'] as $label=>$number)
	$spatialCoverage .= "$label = $number<br>";

    $publisher = "";
    if(!empty($video['snippet']['channelTitle']))
       $publisher .= $video['snippet']['channelTitle']."<br>published via Vimeo.com"; 
    

    if(isset($video['status']['license']))
      {
	switch($video['status']['license']) 
	  {
	  case "vimeo":
	    $license = '<a href="https://www.vimeo.com/static?template=terms">Standard Vimeo License</a>';
	    break;

	  case "creativeCommon":
	      $license='<a href="http://creativecommons.org/licenses/by/3.0/legalcode">Creative Commons License</a>';
	    break;

	  default:
	    $license="";

	  }
      } else { $license = ""; }
    

    if ($video['contentDetails']['licensedContent'])
      {
	$license .= "<br>This video represents licensed content on Vimeo, meaning that the content has been claimed by a Vimeo content partner.";
	$rightsHolder = "Rights reserved by a third party";
      }else
      {
	$rightsHolder = "";
      }

    $maps = array(
		  "Dublin Core"=>array(
				       "Title"=>array($video['snippet']['title']),
				       "Description"=>array($video['snippet']['description']),
				       "Date"=>array($datePublished),
				       "Source"=>array('http://Vimeo.com'),
				       "Rights"=>array($license)
				       )
		  );

    if(!empty($ownerRole))
      $maps['Dublin Core'][$ownerRole]=array($publisher);

    if (plugin_is_active('DublinCoreExtended'))
      {
	$maps["Dublin Core"]["License"]=array($license);
	$maps["Dublin Core"]["Rights Holder"]=array($rightsHolder);
	$maps["Dublin Core"]["Date Submitted"]=array($datePublished);
	//$maps["Dublin Core"]["Date Created"]=array($dateRecorded);
	//$maps["Dublin Core"]["Spatial Coverage"]=array($spatialCoverage);
      }

    if(!element_exists(ElementSet::ITEM_TYPE_NAME,'Player'))
        static::_addPlayerElement();
//      throw new Exception('Metadata element missing for embedded video html');

    $playerHtml = str_replace('/>','></iframe>',$video['player']['embedHtml']);

    $maps[ElementSet::ITEM_TYPE_NAME]["Player"]=array($playerHtml);
      
    $Elements = array();

    $db = get_db();
    $elementTable = $db->getTable('Element');
    
    foreach ($maps as $elementSet=>$elements)
      {
	foreach($elements as $elementName => $elementTexts)
	  {
	    $element = $elementTable->findByElementSetNameAndElementName($elementSet,$elementName);
	    $elementID = $element->id;

	    $Elements[$elementID] = array();
	    if(is_array($elementTexts))
	      {
		foreach($elementTexts as $elementText)
		  {
		    //check for html tags
                      if($elementText != strip_tags($elementText)) {
		      //element text has html tags
		      $html = "1";
		    }else {
		      //plain text or other non-html object
		      $html = "0";
		    }

		    $Elements[$elementID][] = array(
						    'text' => $elementText,
						    'html' => $html
						    );
		  }
	      }
	  }
      }

    $tags = "";
    if(isset($video['snippet']->tags))
      {
	foreach($video['snippet']->tags as $tag)
	  {
	    $tags .= $tag;
	    $tags .=",";
	  }
    
	$tags = substr($tags,0,-2);
      }
    $returnPost = array(
			 'Elements'=>$Elements,
			 'item_type_id'=>'3',      //a moving image
			 'tags-to-add'=>$tags,
			 'tags-to-delete'=>'',
			 'collection_id'=>$collection
			 );
    if($public)
      $returnPost['public']="1";

    $i=0;
    $maxwidth=0;
    foreach($video['snippet']->thumbnails as $key => $file)
      {
	if($file['width']>$maxwidth)
	  $i = $key;
      }

    $returnFiles = array($video['snippet']->thumbnails->default->url);

    return(array(
		 'post' => $returnPost,
		 'files' => $returnFiles
		 ));

  }


}