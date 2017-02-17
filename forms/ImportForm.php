<?php
/**
 * VimeoImport Form for defining import parameters
 *
 * @package     VimeoImport
 * @copyright   2014 UCSC Library Digital Initiatives
 * @license     
 */

/**
 * VimeoImport form class
 */
class Vimeo_Form_Import extends Omeka_Form
{

    /**
     * Construct the import form.
     *
     *@return void
     */
    public function init()
    {
        parent::init();
        $this->_registerElements();
    }

    /**
     * Define the form elements.
     *
     *@return void
     */
    private function _registerElements()
    {
      //hashed anti-csrf nonce:
      //$this->addElement('hash', 'vimeoNonce', array('salt'=>'oilyravinecakes'));

      //URL:
      $this->addElement('text', 'vimeourl', array(
						    'label'         => __('Vimeo URL'),
						    'description'   => __('Paste the full url of the Vimeo video you would like to import'),
						    'validators'    =>array(
                                array('callback',false,array('callback'=>array($this,'validateVimeoUrl'),'options'=>array()))
                            ),
						    'order'         => 1,
						    'required'      => true
						    )
			);

	// Collection:
        $this->addElement('select', 'vimeocollection', array(
							'label'         => __('Collection'),
							'description'   => __('To which collection would you like to add the Vimeo video?'),
							'value'         => '0',
							'order'         => 2,
							'multiOptions'       => $this->_getCollectionOptions()
							)
			  );

	// User Role:
        $this->addElement('select', 'vimeouserrole', array(
								'label'         => __('User Role'),
								'description'   => __('Which role does the Vimeo user/channel play in the creation of the new Omeka item?'),
								'value'         => 'Publisher',
								'order'         => 3,
								
								'multiOptions'       => $this->_getRoleOptions()
								)
			  );


     
        // Visibility (public vs private):
        $this->addElement('checkbox', 'vimeopublic', array(
            'label'         => __('Public Visibility'),
            'description'   => __('Would you like to make the video public in Omeka?'),
            'checked'         => 'checked',
	    'order'         => 4
							   )
			  );

	$this->addElement('hash','vimeo_token');

        // Submit:
        $this->addElement('submit', 'vimeo-import-submit', array(
            'label' => __('Import Video')
        ));

	//Display Groups:
        $this->addDisplayGroup(
			       array(
				     'vimeourl',
				     'vimeocollection',
				     'vimeouserrole',
				     'vimeopublic'
				     ),
			       'fields'
			       );

        $this->addDisplayGroup(
			       array(
				     'vimeo-import-submit'
				     ), 
			       'submit_buttons'
			       );

    }

    /**
     *Process the form data and import the photos as necessary
     *
     *@return bool $success true if successful 
     */
    public static function ProcessPost()
    {
      $_REQUEST['vimeourl'] = self::_resolveShortUrl($_REQUEST['vimeourl']);
      try {
          if(self::_importSingle())
	      return('Go to Items or Collections to view imported videos.');
      } catch(Exception $e) {
          throw new Exception('Error importing video. '.$e->getMessage());
      }
      return(true);
    }


    /**
   * Import a single video in real time (not in the background).
   *
   * This function relies on the import form output being in the
   * $_POST variable. The form should be validated before calling this.
   *
   * @return bool $success true if no error, false otherwise
   */
  private static function _importSingle()
    {
      require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'import.php';

      $public = isset($_REQUEST['vimeopublic']) ? $_REQUEST['vimeopublic'] : false; 

      try{
          $videoID = VimeoImport_ImportHelper::ParseURL($_REQUEST['vimeourl']);
          $response =  VimeoImport_ImportHelper::GetVideo(
              $videoID,
              $_REQUEST['vimeocollection'],
              $_REQUEST['vimeouserrole'],
              $_REQUEST['vimeopublic']
          );

          $post = $response['post'];
          $files = $response['files'];
      } catch (Exception $e) {
          throw $e;
      }

      $record = new Item();
      
      $record->setPostData($post);
   
      if (!$record->save(false)) {
          throw new Exception($record->getErrors());
      }
      
      if(!empty($files)&&!empty($record))
          {
              if(!insert_files_for_item($record,'Url',$files))
                  throw new Exception("Error attaching files");
          }
      return(true);
    }

  /**
   * Get an array to be used in formSelect() containing possible user roles.
   * 
   * @return array $options An associative array mapping dublin core elements
   * which could be associated with the Vimeo usernames, to their display 
   * values in a dropdown menu.
   */
    private function _getRoleOptions()
    {
      $options = array(
		     '0'=>'No Role',
		     'Contributor'=>'Contributor',
		     'Creator'=>'Creator',
		     'Publisher'=>'Publisher'
		     );
    return $options;
    }

    /**
     * Overrides standard omeka form behavior to fix radio display bug
     * 
     * @return void
     */
    public function applyOmekaStyles()
    {
        foreach ($this->getElements() as $element) {
            if ($element instanceof Zend_Form_Element_Submit) {
                // All submit form elements should be wrapped in a div with 
                // no class.
                $element->setDecorators(array(
                    'ViewHelper', 
                    array('HtmlTag', array('tag' => 'div'))
					      ));
            } else if($element instanceof Zend_Form_Element_Radio) {
                // Radio buttons must have a 'radio' class on the div wrapper.
                $element->getDecorator('InputsTag')->setOption('class', 'inputs radio five columns alpha');
		$element->getDecorator('FieldTag')->setOption('id', $element->getName().'field');
                $element->setSeparator('');
            } else if ($element instanceof Zend_Form_Element_Hidden 
                    || $element instanceof Zend_Form_Element_Hash) {
                $element->setDecorators(array('ViewHelper'));
            }
        }
    }


  /**
   * Get an array to be used in formSelect() containing all collections.
   * 
   * @return array $options An associative array mapping collection IDs
   *to their titles for display in a dropdown menu
   */
    private function _getCollectionOptions()
    {
      $collections = get_records('Collection',array(),'0');
      $options = array('0'=>'Assign No Collection');
      foreach ($collections as $collection)
	{
	  $titles = $collection->getElementTexts('Dublin Core','Title');
	  if(isset($titles[0]))
	    $title = $titles[0];
	  $options[$collection->id]=$title;
	}

      return $options;
    }

  /**
   * Resolve a shortened URL and return the full url
   * 
   * @param string $shortUrl The shortened Flickr url of the photo to import.
   * @return string $fullUrl The full Flickr url of the photo to import.
   */
  private static function _resolveShortUrl($shortUrl)
  {
    //if the url contains 'vimeo.com', it's not a short url. just return it.
    if(strpos($shortUrl,'vimeo.com'))
      return($shortUrl);

    $fullUrl = self::_resolveRedirect($shortUrl);
    return $fullUrl;
  }

/**
   * Resolve a redirect and return the redirected url
   * 
   * @param string $url The url which is redirected.
   * @return string $fullUrl The destination url.
   */
  private static function _resolveRedirect($url)
  {
    $headers = get_headers($url);
    $headers = array_reverse($headers);
    foreach($headers as $header) {
      if (strpos($header, 'Location: ') === 0 ) {
	$fullUrl = "https://flickr.com".str_replace('Location: ', '', $header);
	break;
      }
    }
    return $fullUrl;
  }

    /**
     * Validate the vimeo url
     *
     *@param string $url The url to be validated
     *@param array $args An empty options array for now.
     *@return bool $valid Indicates whether this url points to a valid vimeo
     */
    public function validateVimeoUrl($url,$args){
      if(!strpos($url,'vimeo.com'))
          return false;
      return true;
    }




}
