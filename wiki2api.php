<?php /* 311 Lines */

/* PHP-Wiki-API: This is a simple class to get short Wikipedia info boxes from a given Keyword.
 *
 * @package    PHP-Wiki-API
 * @copyright  Copyright (c) 2019 Igor Gaffling <pro@gaffling.com>
 * @license    https://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt  LGPL License
 * @version    Release: @1.0@
 * @link       https://github.com/gaffling/PHP-Wiki-API
 * @since      Class available since Release 1.0
 *
 * @example    <php>
 *             require_once __DIR__.'/wiki2api.php'; // Include the Wikipedia API Class
 *             $wiki = new wiki();                   // Start the Wikipedia API Class
 *             echo $wiki->api($_GET['q']);          // Output the API Response
 *             </php>
 */


class wiki
{  

  // Read and set Parameters
  public function __construct($params=array())
  {
    
    // Default Values
    $defaults = array(
      'language'      => 'de',
      'userAgent'     => 'WikiBot/1.0 (+http://'.$_SERVER['SERVER_NAME'].'/)',
      'betterResults' => true,
      'proxy'         => '',
      'imageProxy'    => true,
      'DEBUG'         => '',
    );
    
    // Merge Parameters and Defaults
    $this->params = array_merge($defaults, $params);
  }

  // Helper Function to get the Content from the API URL
  private function getContent($url, $user_agent, $proxy='')
  {

    // Hopfully we run PHP 4 >= 4.3.0
    if (function_exists('file_get_contents'))
    {
      
      // Set User-Agent and Proxy
    	$context = array (
        'http' => array (
          'user_agent'      => $user_agent,
          'proxy'           => $proxy, 
          'request_fulluri' => true,
        ),
      );
      
      // Build Stream Context
      $context = stream_context_create ($context);
      
      // Use file_get_contents() Function and hide Error with @
      $content = @file_get_contents($url, NULL, $context);
    }
    else // We run PHP < 4.3.0 - OMG :-o
    {
      
      // Ini Var
      $content = ''; 
      
      // Open URL and hide Error with @
      if($handle = @fopen($url, 'r'))
      {
        
        // While there is Data
        while (!feof($handle))
        { 
          
          // Read the Data-Line
          $line = fgets($handle, 4096);
          
          // Add the Data-Line to the Content Var
          $content .= $line; 
        }
        
        // Better Close the FileHandle after the fgets()
        fclose($handle);
      }
    }
    
    // The Function returns the Content
    return $content;
  }

  // Call the API Main Function
  public function api($query)
  {

    // Ini Vars
    $text = $image = $description = '';

    // Convert Query to Lowercase for Headline
    $strtolower = mb_strtolower($query);

    // Convert Headlie to UTF-8 Uppercase Words
    $headline = mb_convert_case($strtolower, MB_CASE_TITLE, 'UTF-8');

    // If Query is complete Uppercase make also complete Uppercase Headline
    if ($query === strtoupper($query))
    { 
      $headline = mb_strtoupper($query);
    }

    // Replace spaces in Query to Underscore and use Uppercase Words from Headline 
    $query = str_replace(' ', '_', $headline);

    // In DEBUG Mode print Query
    if ($this->params['DEBUG']=='KEY' || $this->params['DEBUG']=='ALL')
    {
      echo '<tt><b>Search-Keyword </b><xmp>#'.$query.'#</xmp></tt>';
    }

    // First search the API if betterResults==true
    if ($this->params['betterResults'] == true)
    {

      // Wikipedia API URL 1 - https://en.wikipedia.org/w/api.php
      $url = 'https://'.$this->params['language'].'.wikipedia.org/w/api.php'.
             '?action=query&format=json&list=search&srsearch=intitle:'.$query.
             '&maxlag=1'; /* stop if wiki server is busy */
      
      // If API Call 1 could be reached
      if ($api = $this->getContent($url, $this->params['userAgent'], $this->params['proxy']))
      {

        // Decode the 1 Response
        $data = json_decode($api, TRUE);

        // In DEBUG Mode print 1 Response
        if ($this->params['DEBUG']=='API1' || $this->params['DEBUG']=='ALL')
        {
          echo '<pre><b>Search API-Call (1) Response</b> ';
          echo var_dump($data); 
          echo '</pre>';
        }

        // If there is a search Result
        if (isset($data['query']['search'][0]['title']))
        {

          // Set Headline
          $headline = $data['query']['search'][0]['title'];

          // Set the Query to the first Search Result (and replace Spaces with Underscores)
          $query = str_replace(' ', '_', $data['query']['search'][0]['title']);

          // In DEBUG Mode print Found Keyword
          if ($this->params['DEBUG']=='KEY' || $this->params['DEBUG']=='ALL')
          {
            echo '<tt><b>Found Search-Keyword </b><xmp>#'.$query.'#</xmp></tt>';
          }
        }

        // If Search Result is a 'Did you mean:' Hint
        if ( isset($data['query']['searchinfo']['suggestion']))
        {

          // Set Text Hints depending on selected Language
          if ($this->params['language'] == 'de')
          {
            $suggestionText = 'Meinten Sie: ';
          } 
          else
          {
            $suggestionText = 'Did you mean: ';
          }

          // Remove 'q=' Variable=Value Pair from Querystring
          $QUERY_STRING = preg_replace('/'.('q'?'(\&|)q(\=(.*?)((?=&(?!amp\;))|$)|(.*?)\b)':'(\?.*)').'/i','',$_SERVER['QUERY_STRING']);

          // Delete 'intitle:' from Suggestion Keyword
          $suggestion = str_replace('intitle:','',$data['query']['searchinfo']['suggestion']);

          // Make Suggestion UTF-8 Uppercase Words
          $suggestion = mb_convert_case($suggestion, MB_CASE_TITLE, 'UTF-8');

          // Make HTML Link for Suggestion
          $description = $suggestionText.'<a href="?q='.
                         str_replace(' ', '_', $suggestion).$QUERY_STRING.'">'.$suggestion.'</a>';                         
        }
      }
    }

    // Wikipedia API URL 2 - https://en.wikipedia.org/w/api.php
    $url = 'https://'.$this->params['language'].
           '.wikipedia.org/api/rest_v1/page/summary/'.$query.
           '?maxlag=1'; /* stop if wiki server is busy */

    // If API Call 2 could be reached
    if ($api = $this->getContent($url, $this->params['userAgent'], $this->params['proxy']))
    {
      // Decode the 2 Response
      $data = json_decode($api, TRUE);

      // In DEBUG Mode print 2 Response
      if ($this->params['DEBUG']=='API2' || $this->params['DEBUG']=='ALL')
      {
        echo '<pre><b>Main API-Call (2) Response</b> ';
        echo var_dump($data);
        echo '</pre>';
      }

      // If there is an Image in the Search Result
      if (isset($data['originalimage']['source']))
      {

        // If the DSGVO imageProxy should be use define it
        $proxy = '';
        if ($this->params['imageProxy']==TRUE)
        {
          $proxy = 'image_proxy.php?url=';
        }

        // Build HTML for Image
        $image = '<img src="'.$proxy.$data['thumbnail']['source'].'" />';
      }

      // Correct the Text
      $text = str_replace('#', ': ', $data['extract_html']);

      // If there is a Description
      if (isset($data['description']))
      {

        // Correct the Description depending on selected Language
        $description = str_replace(
          array(
            'Wikimedia-Begriffsklärungsseite',
    'Disambiguation page providing links to topics that could be referred to by the same search term'
          ),
          array(
            'kann sich auf Folgendes beziehen',
            'may refer to the following'
          ),
          $data['description']
        );

        // Set Keyword to UTF-8 Uppercase Words of Query
        $keyword = mb_convert_case($strtolower, MB_CASE_TITLE, 'UTF-8');

        // Highlight the Query in the Text and Delete some Text
        $text = str_replace(
          array($keyword, ' may refer to', ' steht für:'), 
          array('<b class="hint">'.$keyword.'</b>', '', ''), 
          $text
        );
      }

      // If there is no Article Text set a Default depending on selected Language
      // e.g. q=Leonardo%20di%20caprio&language=de
      if ($text == '' && $this->params['language'] == 'de')
      {
        $text = 'Zu diesem Stichwort ist kein Artikel vorhanden.';
      }
      else if($text == '')
      {
        $text = 'There is no article available for this keyword.';    
      }      
    }

    // Build the HTML Output
    if ($this->params['language']=='de')
    {
      $moreAbout = 'Mehr &uuml;ber';
      $from = 'bei';    
    }
    else
    {
      $moreAbout = 'More about';
      $from = 'from';    
    }

    // Without any Search Result return nothing
    if ($text == '' && $description == '')
    {
      return '';
    }

    // With a Search Resuld build a Footer Link
    if ($text != '')
    {
      $footer = $moreAbout.' &raquo;'.$headline.'&laquo; '.$from;
      $url = 'https://'.$this->params['language'].'.wikipedia.org/wiki/'.$query;
    }
    else if ($description != '')
    {
      // Footer Link for Suggestion-Link
      $footer = '';
      $url = 'https://'.$this->params['language'].'.wikipedia.org/';
    }

    // Use the Template
    ob_start();
    include 'wiki2tpl.phtm';
    
    // Return the HTML
    return ob_get_clean();    

  }

}
