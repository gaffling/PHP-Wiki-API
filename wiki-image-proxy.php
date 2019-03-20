<?php /* 150 Lines */

/*
    Datenschutz-Proxy für Bilder
    ----------------------------
    Wieso sollte ein Proxy für die Ausgabe der Bilder verwendet werden?
    Um der DSGVO gerecht zu werden, 
    sollten Bilder von fremden Server über einen Datenschutz-Proxy 
    ausgegeben werden.    
    Bekanntlich werden die von der Wikipeda API bereitgestellten Bilder 
    direkt von den Wikipedia-Servern ausgeliefert. Im Hinblick auf die 
    Datenschutz-Grundverordnung ist dies jedoch problematisch, 
    da Wikipedia dadurch Zugriff auf die IP-Adressen der Seitenbesucher hat.
    Um das zu verhindern, kann dieser Datenschutz-Proxy genutzt werden, 
    wodurch die Bilder über dieses PHP-Script abgerufen werden. 
    Dadurch erhält Wikipedia lediglich Zugriff auf die IP des abrufenden
    Webservers, und nicht die IP des Bild ansehenden Seitenbesuchers.
    
    Verwendung des Datenschutz-Proxy
    Die Funktion muss in der Haupt-Klasse über die Einstellung
    (“imageProxy” = true) aktiviert werden. 
*/

# Check for url parameter
$url = isset( $_GET['url'] ) ? $_GET['url'] : null;
if (!isset($url) or preg_match('#^https?://#', $url) != 1)
{
  header('HTTP/1.1 404 Not Found');
  exit;
}

# Check if the client already has the requested item
if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) or 
    isset($_SERVER['HTTP_IF_NONE_MATCH']))
{
    header('HTTP/1.1 304 Not Modified');
    exit;
}

# Check if cURL exists, and if so: use it
if (function_exists('curl_version'))
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ImageProxy/1.0 (+http://'.$_SERVER['SERVER_NAME'].'/');
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 12800);
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 
      function($DownloadSize, $Downloaded, $UploadSize, $Uploaded)
      { 
        return ($Downloaded > 1024 * 512) ? 1 : 0; 
      }
    ); // max 500kb
    $out = curl_exec ($ch);
    curl_close ($ch);
    
    // Read all headers
    $file_array = explode("\r\n\r\n", $out, 2);
    $header_array = explode("\r\n", $file_array[0]);
    foreach($header_array as $header_value)
    {
        $header_pieces = explode(': ', $header_value);
        if(count($header_pieces) == 2)
        {
            $headers[$header_pieces[0]] = trim($header_pieces[1]);
        }
    }
    
    // Check if location moved, and if so: redirect
    if (array_key_exists('Location', $headers)) {
        $newurl = urlencode($headers['Location']);
        header("HTTP/1.1 301 Moved Permanently");
        if ((isset($_SERVER['HTTP_X_FORWARDED_PROTO']) and 
                   $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') or 
            (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') or
            (isset($_SERVER['HTTPS']) and $_SERVER['SERVER_PORT'] == 443))
        {
            $protocol = 'https://';
        }
        else
        {
            $protocol = 'http://';
        }
        $PROXY = $protocol.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].'?url=';
        header('Location: ' . $PROXY . $newurl);
    }
    else // Check if it's an image and output all headers
    {
        if (array_key_exists('Content-Type', $headers))
        {
            $ct = $headers['Content-Type'];
            if (preg_match('#image/png|image/.*icon|image/jpe?g|image/gif#', $ct) !== 1)
            {
                header('HTTP/1.1 404 Not Found');
                exit;
            }
            header('Content-Type: ' . $ct);
        }
        if (array_key_exists('Content-Length', $headers))
        {
            header('Content-Length: ' . $headers['Content-Length']);
        }
        if (array_key_exists('Expires', $headers))
        {
            header('Expires: ' . $headers['Expires']);
        }
        if (array_key_exists('Cache-Control', $headers))
        {
            header('Cache-Control: ' . $headers['Cache-Control']);
        }
        if (array_key_exists('Last-Modified', $headers))
        {
            header('Last-Modified: ' . $headers['Last-Modified']);
        }
        
        // Output Image
        echo $file_array[1];
    }
}
else // No cURL so use readfile()
{
  
    // Check if it's an image
    if ($imgInfo = @getimagesize( $url ))
    {
        if (preg_match('#image/png|image/.*icon|image/jpe?g|image/gif#', $imgInfo['mime']) !== 1)
        {
            header('HTTP/1.1 404 Not Found');
            exit;
        }
        
        // Output simple header
        header( 'Content-type: ' . $imgInfo['mime'] );
        
        // Output Image
        readfile( $url );
    }
    else
    {
        // No Image Found
        header('HTTP/1.1 404 Not Found');
        exit;
    }
}
