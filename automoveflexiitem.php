<?php
defined('_JEXEC') or die;

class PlgSystemAutomoveflexiitem extends JPlugin
{
    protected $autoloadLanguage = true;
    public function __construct( &$subject, $config )
    {
        parent::__construct( $subject, $config );
 
    }
    public function onAfterInitialise ()
    {
        $datemode = $this->params->get('datemode','');//0 joomla unplishing date or 1 for flexicontent date
        $fielddateid = $this->params->get('fielddateid','');//id of flexicontent date field
        
        // init le log file
            $rotatDate = JFactory::getDate()->format('Y-m');
            $logLevel = JLog::ALL ^ JLog::WARNING ^ JLog::DEBUG;
            Jlog::addLogger (
                array(
                    'text_file' => 'automove_'.$rotatDate.'.log.php',
                 ),
                $logLevel
            );

        
        $srvdate = $this->_getDateAction();
        
         // ecrit dans le journal de log la 1ere trace
            $logEntry = new JlogEntry("Nouveau traitement autoMove a la date du ".$srvdate, JLog::NOTICE, "automove");
            Jlog::add($logEntry);

        $listContents = $this->_getItemsToMove ($srvdate, $datemode, $fielddateid);
        
         // ecrit dans le journal de log le nbr de contenu to move
            $logEntry = new JlogEntry(count($listContents)." contenus a deplacer", JLog::NOTICE, "automove");
            Jlog::add($logEntry);
        
        if (function_exists('dump')) dump($listContents, 'Des données sont à archivées');
        if($listContents)
            $this->_moveItems($listContents, $datemode, $fielddateid);
         
        // ecrit dans le journal de log la derniere trace
            $logEntry = new JlogEntry("fin traitement autoMove a la date du ".$srvdate, JLog::NOTICE, "automove");
            Jlog::add($logEntry);
   }
    /**
	* Get date and delay
	*/
    private function _getDateAction () {
        $numbdelay = $this->params->get('numdelay', '1');
        $typedelay = $this->params->get('typedelay', '');
        $actiondelay = $this->params->get('actiondelay', '');
        $delay = $numbdelay . $typedelay; // add delay to sql for get item
        $serveurdateinit = date('Y-m-d');
        if ($actiondelay !=0){
        $serveurdate = new DateTime($serveurdateinit);
        $serveurdate->add(new DateInterval('P'.$delay));  
        $serveurdate= $serveurdate->format('Y-m-d');
        }else{
        $serveurdate = $serveurdateinit;
            }
       // if (function_exists('dump')) dump($serveurdateinit, 'date serveur');
       if (function_exists('dump')) dump($serveurdate, 'date serveur + delay');
        return $serveurdate;
    }
   
	/**
	* Get item to move
	*/
	private function _getItemsToMove ($serveurdate, $datemode, $fielddateid) {
		$methode = $this->params->get('catmethode', '1');       // 1 include or 0 exclude categories
		$moved_cat = $this->params->get('moved_category', '');  // categories to get item
		$limit = 'LIMIT '.$this->params->get('limit', '20').''; // number of item to get

//TODO il faudra refaire l'objet query en utilisant les methodes d'abstraction SQL ... on en parle dans la semaine
		// selection des champs
		$datsource = 'a.id, a.title, a.publish_down, a.catid FROM #__content AS a';
//TODO tu es sur que la liste des champs du SELECT change selon le datmode ??? pas facile a maintenir ca !
		if ($datemode != 0) {
			$datsource = 'a.id, a.title, a.publish_down, b.field_id, b.value, a.catid FROM #__content AS a ' .
						'LEFT JOIN #__flexicontent_fields_item_relations AS b ON b.item_id = a.id';
			}

		// construction des clauses WHERE
		$tWheres = array();

		// clause sur la date de publication
		if ($datemode == 0){
            $tWheres[] = "a.publish_down < '$serveurdate'";
        } else {
            $tWheres[] = "b.value < '$serveurdate'";
        }
		 
		// clause sur les categories
		$categoriesID = implode(',', $moved_cat);
		if (function_exists("dump")) dump($categoriesID, 'catid');
		if ($methode == 0) {
			$tWheres[] = "a.catid IN (".$categoriesID.")";
		} else {
			$tWheres[] = "a.catid NOT IN (".$categoriesID.")";
		}
		if (function_exists("dump")) dump($datemode, "datemode");

		// clause sur l'utilisation d'un champ date flexi
		if ($datemode == 1)
			$tWheres[] = "b.field_id = ".$fielddateid;

		$db = JFactory::getDBO();
		// construction de la requete SQL
		$sWheres = implode(" AND ", $tWheres);
		$query = "SELECT $datsource WHERE $sWheres $limit";
		$db->setQuery($query);
		if (function_exists("dump")) dump($query, 'requete');
		//if (function_exists("dump")) dump($query->__toString(), 'requete toString');
		$selectarticle = $db->loadObjectList();
		//if (function_exists("dump")) dump($selectarticle, 'export de données');
		return $selectarticle;
	}

    /**
	* Move item
	*/
    private function _moveItems ($listContents, $datemode, $fielddateid) {
        $movecat = $this->params->get('movecat','');//0 not move article or 1 for move
        $target_cat = $this->params->get('target_category', '');//id of target move categorie
        $movesubcat = $this->params->get('movesubcat','');//0 not move article in subcator 1 for move
        $target_subcat = $this->params->get('target_subcategory', '');//id of target move subcategorie
        $state = $this->params->get('changestate', '');//changing state of article
        $cleardate = $this->params->get('cleardate', '');//clear date nothing, 0 unpblished, 1 published, -1 archived, -2 trashed
        
       
        // construction des clauses WHERE
		$tWheres = array();

		// clause clear date
        if ($cleardate == 1 && $datemode == 0){ //clear joomla unpublished date
                $tWheres[]="publish_down = '0000-00-00 00:00:00'";
         }
        
        switch ($state){//changing state
            case '0': 
                $tWheres[]="state ='0'";
            break;
                case '1': 
                $tWheres[]="state ='1'";
            break;
            case '-1': 
                $tWheres[]="state ='-1'";
            break;
            case '-2': 
                $tWheres[]="state ='-2'";
            break;
           // TODO case 'nothing': soucis dans le WHERE si rien
            //    $tWheres[]="";
            //break;
        }
        if ($movecat == 1 && $movesubcat == 0){//move article
            $tWheres[]="catid ='$target_cat'";
        }elseif ($movecat == 1 && $movesubcat == 1){
            $tWheres[]="catid =$target_cat ".
                        "LEFT JOIN ";//TODO adding FLEXIContent subcat
        }else {
            $tWheres[]="";//TODO test si on deplace pas les cats
        }
        
        $sWheres = implode(" , ", $tWheres);
        
      foreach ($listContents as $article){
          
        //CLEAN MULTI-CAT
        if ($movecat == 1 ){
          $db1 = JFactory::getDBO();
          $query1 = "DELETE FROM #__flexicontent_cats_item_relations  WHERE itemid='$article->id' ";
          $db1->setQuery($query1);
          $result1 = $db1->execute();
               
          $db2 = JFactory::getDBO();
          $query2 = "INSERT INTO #__flexicontent_cats_item_relations VALUES ($target_cat , $article->id , 0)";
          $db2->setQuery($query2);
          $result2 = $db2->execute();
         }
          
        //UPDATE com_content
          $db3 = JFactory::getDBO();
          $query3 = "UPDATE #__content SET $sWheres WHERE id ='$article->id' ";
          $db3->setQuery($query3);
          $result3 = $db3->execute();  
          
         //UPDATE flexicontent tmp
          $db4 = JFactory::getDBO();
          $query4 = "UPDATE #__flexicontent_items_tmp SET $sWheres WHERE id ='$article->id' ";
          $db4->setQuery($query4);
          $result4 = $db4->execute();
          
          //UPDATE date field
          if ($cleardate == 1 && $datemode == 1){//clear flexicontent date field
            $db5 = JFactory::getDBO();
            $querydateflexi = "UPDATE #__flexicontent_fields_item_relations SET value=''  WHERE field_id= $fielddateid AND item_id =$article->id";
             if (function_exists('dump')) dump($querydateflexi, 'requette update date flexi');
            $db5->setQuery($querydateflexi);
            $result5 = $db5->execute();
          }
          
        }
    }
}
