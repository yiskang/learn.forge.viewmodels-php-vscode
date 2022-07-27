<?php
namespace Autodesk\ForgeServices;
use Autodesk\Forge\Client\Api\BucketsApi;
use Autodesk\Forge\Client\Model\PostBucketsPayload;
use Autodesk\Forge\Client\Api\ObjectsApi;

function GUID()
{
    if (function_exists('com_create_guid') === true)
    {
        return trim(com_create_guid(), '{}');
    }

    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
}

class DataManagement{
    public function __construct(){
        set_time_limit(0);
    }

    public function createOneBucket(){
         global $twoLeggedAuth;
         $accessToken = $twoLeggedAuth->getTokenInternal();

         // get the request body
         $body = json_decode(file_get_contents('php://input', 'r'), true);

         $bucketKey = ForgeConfig::$prepend_bucketkey?(strtolower(ForgeConfig::getForgeID()).'_'.$body['bucketKey']):$body['bucketKey'];
         // $policeKey = $body['policyKey'];
         $policeKey = 'transient';

         $apiInstance = new BucketsApi($accessToken);
         $post_bucket = new PostBucketsPayload();
         $post_bucket->setBucketKey($bucketKey);
         $post_bucket->setPolicyKey($policeKey);

         try {
             $result = $apiInstance->createBucket($post_bucket);
             print_r($result);
         } catch (Exception $e) {
             echo 'Exception when calling BucketsApi->createBucket: ', $e->getMessage(), PHP_EOL;
         }
      }


      /////////////////////////////////////////////////////////////////////////
      public function getBucketsAndObjects(){
         global $twoLeggedAuth;
         $accessToken = $twoLeggedAuth->getTokenInternal();

         $id = $_GET['id'];
         try{
             if ($id === '#') {// root
                 $apiInstance = new BucketsApi($accessToken);
                 $result = $apiInstance->getBuckets();
                 $resultArray = json_decode($result, true);
                 $buckets = $resultArray['items'];
                 $bucketsLength = count($buckets);
                 $bucketlist = array();
                 for($i=0; $i< $bucketsLength; $i++){
                     $cbkey = $buckets[$i]['bucketKey'];
                     $exploded = explode('_', $cbkey);
                     $cbtext = ForgeConfig::$prepend_bucketkey&&strpos($cbkey, strtolower(ForgeConfig::getForgeID())) === 0? end($exploded):$cbkey;
                     $bucketInfo = array('id'=>$cbkey,
                                         'text'=> $cbtext,
                                         'type'=>'bucket',
                                         'children'=>true
                     );
                     array_push($bucketlist, $bucketInfo);
                 }
                 print_r(json_encode($bucketlist));
             }
             else{
                 $apiInstance = new ObjectsApi($accessToken);
                 $bucket_key = $id;
                 $result = $apiInstance->getObjects($bucket_key);
                 $resultArray = json_decode($result, true);
                 $objects = $resultArray['items'];

                 $objectsLength = count($objects);
                 $objectlist = array();
                 for($i=0; $i< $objectsLength; $i++){
                     $objectInfo = array('id'=>base64_encode($objects[$i]['objectId']),
                                         'text'=>$objects[$i]['objectKey'],
                                         'type'=>'object',
                                         'children'=>false
                     );
                     array_push($objectlist, $objectInfo);
                 }
                 print_r(json_encode($objectlist));
             }
         }catch(Exception $e){
             echo 'Exception when calling ObjectsApi->getObjects: ', $e->getMessage(), PHP_EOL;
         }

      }


      public function uploadFile(){
          global $twoLeggedAuth;
          $accessToken = $twoLeggedAuth->getTokenInternal();

          $body = $_POST;
          $file = $_FILES;

          $apiInstance = new ObjectsApi($accessToken);
          $bucket_key  = $body['bucketKey'];
          $fileToUpload    = $file['fileToUpload'];
          $filePath = $fileToUpload['tmp_name'];
          $content_length = filesize($filePath);

          $chunkSize = 2 * 1024 * 1024;
          $nbChunks = (int)round(0.5 + (double)$content_length / (double)$chunkSize);
          $handle = fopen($filePath, 'r');

          $sessionId = GUID();

          try {
             //$result = $apiInstance->uploadObject($bucket_key, $fileToUpload['name'], $content_length, $file_content);
             for($i = 0; $i < $nbChunks; $i++) {
                //Start position in bytes of a chunk
                $start = $i * $chunkSize;
                //End position in bytes of a chunk
                //(End position of the latest chuck is the total file size in bytes)
                $end = min($content_length, ($i + 1) * $chunkSize) - 1;

                //Identify chunk info. to the Forge
                $range = 'bytes ' . $start . '-' . $end . '/' . $content_length;
                //Steam size for this chunk
                $length = $end - $start + 1;

                echo 'Uploading range： ' . $range;

                $contents = fread($handle, $length);
                $result = $apiInstance->uploadChunk($bucket_key, $fileToUpload['name'], $length, $range, $sessionId, $contents);
                print_r($result);  
             }

             fclose($handle);
          } catch (Exception $e) {
            fclose($handle);
            echo 'Exception when calling ObjectsApi->uploadObject: ', $e->getMessage(), PHP_EOL;
          }
      }
}
