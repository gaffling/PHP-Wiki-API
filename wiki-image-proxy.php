<?php /* 165 Lines */

/**
 * Privacy proxy for images
 *
 * USE: https://yourdomain.top/proxy.php?url=
 *
 * Why should a proxy be used for the output of the images?
 * To comply with the DSGVO, 
 * images from other servers should be outputed via a data protection proxy 
 * As is well known, the images provided by the Wikipeda API are delivered
 * directly from the Wikipedia servers. 
 * In view of the data protection regulation, however, this is problematic, 
 * as it gives Wikipedia access to the IP addresses of visitors to the site.
 * To prevent this, this privacy proxy can be used, 
 * which retrieves the images through this PHP script. 
 * This only gives Wikipedia access to the IP of the requesting 
 * web server, and not the IP of the page visitor viewing the image.
 * 
 * @see https://github.com/gaffling/PHP-Wiki-API/blob/master/wiki-image-proxy.php
 *  
 */

// Check for url parameter
$url = isset( $_GET['url'] ) ? $_GET['url'] : null;
if (!isset($url) or preg_match('#^https?://#', $url) != 1)
{
  header('HTTP/1.1 404 Not Found');
  exit;
}

// Check if the client already has the requested item
if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) or 
    isset($_SERVER['HTTP_IF_NONE_MATCH']))
{
    header('HTTP/1.1 304 Not Modified');
    exit;
}

// Get File Extention from URL
$ext = pathinfo(basename($url), PATHINFO_EXTENSION);

// Check if cURL exists, and if so: use it
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
        return ($Downloaded > 1024 * 512) ? 1 : 0; /* max 512kb */
      }
    );
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
        else
        {
            header('Content-Type: image/' . $ext);
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
        // Check mime
        if (preg_match('#image/png|image/.*icon|image/jpe?g|image/gif#', $imgInfo['mime']) !== 1)
        {
            header('HTTP/1.1 404 Not Found');
            exit;
        }
        
        // Output simple header
        if (isset($imgInfo['mime']) and !empty($imgInfo['mime']) )
        {
            header( 'Content-Type: ' . $imgInfo['mime'] );
        }
        else
        {
            header('Content-Type: image/' . $ext);
        }
        
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
