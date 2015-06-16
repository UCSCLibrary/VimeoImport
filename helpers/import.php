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
    private static $Vimeo_Access_Key = 'c69cf13bd30ed59a6057d6c54a1396b8';
    
    /**
     *Parse the Vimeo url parameter 
     *
     *@return $string $setID A unique identifier for the Vimeo collection
     */
    public static function ParseURL($url)
    {
        if(is_numeric($url))
            return($url);
        $path = parse_url($url,PHP_URL_PATH);
        return(str_replace('/','',$path));
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

    public static function Fetch($url){      

        if(strpos($url,'http')===FALSE)
            if(strpos($url,'/')!==0)
                $url = "https://api.vimeo.com/".$url;
            else
                $url = "https://api.vimeo.com".$url;
        
        $authorization = "Authorization: Bearer ".get_option('vimeo_token');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result=curl_exec($ch);
        curl_close($ch);
        return json_decode($result,true);
    }

    public static function GetVimeoLicense($code)
    {
        $licenses = self::Fetch('creativecommons');
        foreach($licenses['data'] as $license) 
            if($license['code']==$code)
                return $license['name'];
            else
                return "";
    }

    /**
     *Fetch metadata from a Vimeo video and prepare it
     *
     *@param string $itemID The Vimeo video ID from which to extract metadata
     *@param int $collection The ID of the collection to which to add the new item
     *@param string $ownerRole The name of the dublin core field to which to 
     *add the Vimeo user info
     *@param boolean public Indicates whether the new omeka item should be public
     *@return array An array containing metadata associated with the 
     *given vimeo video in the correct format to save as an omeka item,
     *and urls of files associated
     */
    public static function GetVideo($itemID,$collection=0,$ownerRole='Publisher',$public=0)
    {
        $video = self::Fetch("videos/$itemID");
        $publisher = "Vimeo.com";    

        if(!isset($video['license']) || !$video['license'] || $video['license']==="null") {
            $license = self::GetVimeoLicense($video['license']);
            
            $maps = array(
                "Dublin Core"=>array(
                    "Title"=>array($video['name']),
                    "Description"=>array($video['description']),
                    "Date"=>array($video['created_time']),
                    "Source"=>array($video['link']),
                    "Rights"=>array($license)
                )
            );

            if(!empty($ownerRole))
                $maps['Dublin Core'][$ownerRole]=array($video['user']['name'].' ('.$video['user']['link'].')');
            
            if (plugin_is_active('DublinCoreExtended'))
                {
                    $maps["Dublin Core"]["License"]=array($license);
                    $maps["Dublin Core"]["Date Created"]=array($video['created_time']);
                }

            if(!element_exists(ElementSet::ITEM_TYPE_NAME,'Player'))
                static::_addPlayerElement();

            $maps[ElementSet::ITEM_TYPE_NAME]["Player"]=array($video['embed']['html']);
            
            if(element_exists(ElementSet::ITEM_TYPE_NAME,'Duration'))
                $maps[ElementSet::ITEM_TYPE_NAME]["Duration"]=array($video['duration']);
            
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
            
            $tags='';
            if(isset($video['tags']))
                {
                    foreach($video['tags'] as $tag)
                        {
                            $tags .= $tag['tag'];
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
            
            $pictures = self::fetch("videos/$itemID/pictures");
            $maxwidth=0;
            $i=0;
            $returnFiles = array();
            if($pictures['total']>0)
                foreach($pictures['data'] as $picture){
                    foreach($picture['sizes'] as $key => $pic)
                        {
                            if($pic['width']>$maxwidth)
                                $i = $key;
                        }
                    $returnFiles[] = $picture['sizes'][$i]['link'];
                }
            $captions = self::Fetch("videos/$itemID/texttracks");
            if($captions['total']>0)
                foreach($captions['data'] as $caption)
                    $returnFiles[] = $caption['link'];

            return(array(
                'post' => $returnPost,
                'files' => $returnFiles
            ));
        }
    }
}