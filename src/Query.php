<?php
namespace Pctco\WebsiteBox;
use QL\QueryList;
use think\facade\Cache;
use Pctco\Verification\Regexp;
use Naucon\File\File;
use Naucon\File\FileWriter;
use Pctco\WebsiteBox\Tools;
use Pctco\Video\Edit as VideoEdit;
/**
* 查询数据
**/
class Query{
   function __construct($options){
      $this->id = '123456';
      $this->url = $options['url'];
      $purl = parse_url($this->url);
      $this->domain = $purl['scheme'].'://'.$purl['host'];
      $this->clone = md5('clone:::'.$this->domain);
      $this->path = 'uploads'.DIRECTORY_SEPARATOR.'website'.DIRECTORY_SEPARATOR.'id'.DIRECTORY_SEPARATOR.$this->id.DIRECTORY_SEPARATOR.'public';
      $this->RootPath = app()->getRootPath().'entrance'.DIRECTORY_SEPARATOR.$this->path;

      $this->tools = new Tools($this);
   }

   /**
   * @name delete
   * @describe 删除资源目录
   * @author
   * @param mixed delete = 删除数据(删除数据后将会初始化数据)
   * @return
   **/
   public function delete(){
      $file = new File(dirname($this->RootPath));
      $file->deleteAll();
      return $this->QueueData([],'delete');
   }
   /**
   * @name find
   * @describe 查找 html 中资源
   * @author
   * @param mixed $html  queue = 开始按照缓存列队中的数据处理
   * @return
   **/
   public function find($html = 'queue',$RegexpType = ['a','img','img_css','img_js','img_link','css','js','video']){
      $IsQueueData = false;
      try {
         if ($html === 'queue') {
            // 有数据 则进行缓存列队中的数据处理
            if (empty($this->QueueData())) {
               $IsQueueData = true;
               // $html = QueryList::get($this->url)->getHtml();
               $html = file_get_contents($this->url);
            }else{
               return $this->NewFile();
            }
         }

         $regexp = new \Pctco\Verification\Regexp($html);

         /************************************************
         * @name [a 标签]
         **/
         $a = [];
         if (in_array('a',$RegexpType)) {
            $find = $regexp->find('html.a.href.link');
            if ($find !== false) {
               $a = $find;
            }
         }

         /************************************************
         * @name [img 标签]
         **/
         $img = [];
         if (in_array('img',$RegexpType)) {
            $find = $regexp->find('html.img.src.link');
            if ($find !== false) {
               $img = $find;
            }
         }

         /**
         * @name [<style> css background: url(img)]
         **/
         if (in_array('img_css',$RegexpType)) {
            // $img = $regexp->find('/url\([\"|\'](.*?[\.gif|\.jpg|\.png|\.jpeg].*?)[\"|\'|?]\)/');
            $find = $regexp->find('/url\([\"|\'](.*?[\.gif|\.jpg|\.png|\.jpeg].*?)[\"|\'|?]\)/');
            if ($find !== false) {
               foreach ($find as $link) {
                  $url = str_replace(['url("',"url('",'")',"')",'url(',')'],'',$link,$count);
                  $img[] = $url;
               }
            }
         }

         /**
         * @name [<script> js img link]
         **/
         if (in_array('img_js',$RegexpType)) {
            $find = $regexp->find('html.script.content');
            if ($find !== false) {
               foreach ($find as $JsScript) {
                  $arr = (new \Pctco\Verification\Regexp($JsScript))->find('html.script.string.img.link');
                  if (is_array($arr)) {
                     foreach ($arr as $link) {
                        if ((new \Pctco\Verification\Regexp($link))->check('format.link.img')) {
                           $img[] = $link;
                        }
                     }
                  }
               }
            }
         }

         /**
         * @name [<link> img link]
         **/
         if (in_array('img_link',$RegexpType)) {
            $find = $regexp->find('html.img.href.link');
            if ($find !== false) {
               $img = array_merge($img,$find);
            }
         }


         /************************************************
         * @name [<link> css]
         **/
         $css = [];
         if (in_array('css',$RegexpType)) {
            $find = $regexp->find('html.css.href.link');
            if ($find !== false) {
               $css = $find;
            }
         }


         /************************************************
         * @name [<script> js]
         **/
         $js = [];
         if (in_array('js',$RegexpType)) {
            $find = $regexp->find('html.script.src.link');
            if ($find !== false) {
               $js = $find;
            }
         }


         /************************************************
         * @name [<video> video]
         **/
         $video = [];
         if (in_array('video',$RegexpType)) {
            $find = $regexp->find('html.video.src.link');
            if ($find !== false) {
               $video = $find;
            }
         }

         $data = [
            'img'   =>   $this->tools->UrlSortOut($img),
            'css'   =>   $this->tools->UrlSortOut($css),
            'js'   =>   $this->tools->UrlSortOut($js),
            'video'   =>   $this->tools->UrlSortOut($video)
         ];
         $dataA = [];
         if ($IsQueueData) {
            $dataA = [
               'a'   =>   $this->tools->UrlSortOut($a)
            ];
         }

         return $this->QueueData(array_merge($data,$dataA),'set');

      } catch (\Exception $e) {
         return $e;
      }

   }


   /**
   * @name Queue Data
   * @describe 列队数据
   * @param mixed $Arr
   * @return String URL
   **/
   public function QueueData($data = [],$op = 'get'){
      if ($op === 'delete') {
         return Cache::store('reptile')->delete($this->clone);
      }
      $GetData = Cache::store('reptile')->get($this->clone);
      $GetData = empty($GetData)?[]:$GetData;
      if ($op === 'set') {
         Cache::store('reptile')->set($this->clone,array_merge($GetData,$data));
         $GetData = Cache::store('reptile')->get($this->clone);
      }
      return $GetData;
   }

   /**
   * @name New File
   * @describe 新建文件
   * @return String URL
   **/
   public function NewFile(){
      $UploadImage = new \Pctco\Storage\App\UploadImage();
      try {
         $data = $this->QueueData();
         /**
         * @if 保存 html 文件
         **/
         if (!empty($data['a'])) {
            // 判断 status = 0
            $IsStatus = array_filter($data['a'], function($isArr) {
               return $isArr['status'] == '0';
            });

            if (!empty($IsStatus)) {
               $key = array_keys($IsStatus)[0];
               $value = $IsStatus[array_keys($IsStatus)[0]];

               $dir = DIRECTORY_SEPARATOR.'theme'.DIRECTORY_SEPARATOR.substr(md5(time()),8,16).'.html';
               if ($key === '/') {
                  $dir = '/index.html';
               }

               $html = file_get_contents($value['url']);

               if (empty($html)) {
                  return '['.date('h:i:sa').']New File Error';
               }

               // 查询其他 a link 资源
               $NewData = $this->find($html);

               /**
               * @name 创建 .html
               * @author
               * @version
               **/
               $FileWriter = new FileWriter($this->RootPath.$dir,'w+');
               $FileWriter->write($html);

               $value['status'] = 1;
               $value['path'] = $dir;
               $data['a'][$key] = $value;
               $this->QueueData(array_merge($NewData,$data),'set');

               return '['.date('h:i:sa').'] $A->status('.$value['status'].')->count('.count($IsStatus).')'.'->'.$key.' = '.$dir;
            }
         }
         /**
         * @if 保存 css 文件
         **/
         if (!empty($data['css'])) {
            // 判断 status = 0
            $IsStatus = array_filter($data['css'], function($isArr) {
               return $isArr['status'] == '0';
            });
            if (!empty($IsStatus)) {
               $key = array_keys($IsStatus)[0];
               $value = $IsStatus[array_keys($IsStatus)[0]];

               // $css = QueryList::get($value['url'])->getHtml();
               $css = file_get_contents($value['url']);

               if (empty($css)) {
                  return '['.date('h:i:sa').']New File Error';
               }

               // 查询其他 css img 资源
               $this->find($css,['css_img']);
               $dir = DIRECTORY_SEPARATOR.'css'.DIRECTORY_SEPARATOR.substr(md5(time()),8,16).'.css';
               /**
               * @name 创建 .css
               * @author
               * @version
               **/
               $FileWriter = new FileWriter($this->RootPath.$dir,'w+');
               $FileWriter->write($css);

               $value['status'] = 1;
               $value['path'] = $dir;
               $data['css'][$key] = $value;
               $this->QueueData($data,'set');
               return '['.date('h:i:sa').'] $Link->status('.$value['status'].')->count('.count($IsStatus).')'.'->'.$key.' = '.$dir;
            }
         }
         /**
         * @if 保存 js 文件
         **/
         if (!empty($data['js'])) {
            // 判断 status = 0
            $IsStatus = array_filter($data['js'], function($isArr) {
               return $isArr['status'] == '0';
            });
            if (!empty($IsStatus)) {
               $key = array_keys($IsStatus)[0];
               $value = $IsStatus[array_keys($IsStatus)[0]];

               $js = file_get_contents($value['url']);

               if (empty($js)) {
                  return '['.date('h:i:sa').']New File Error';
               }

               // 查询其他 js img 资源
               $this->find($js,['js_img']);
               $dir = DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.substr(md5(time()),8,16).'.js';
               /**
               * @name 创建 .css
               * @author
               * @version
               **/
               $FileWriter = new FileWriter($this->RootPath.$dir,'w+');
               $FileWriter->write($js);

               $value['status'] = 1;
               $value['path'] = $dir;
               $data['js'][$key] = $value;
               $this->QueueData($data,'set');
               return '['.date('h:i:sa').'] $Script->status('.$value['status'].')->count('.count($IsStatus).')'.'->'.$key.' = '.$dir;

            }
         }
         /**
         * @if 保存 video 文件
         **/
         if (!empty($data['video'])) {
            // 判断 status = 0
            $IsStatus = array_filter($data['video'], function($isArr) {
               return $isArr['status'] == '0';
            });
            if (!empty($IsStatus)) {
               $key = array_keys($IsStatus)[0];
               $value = $IsStatus[array_keys($IsStatus)[0]];

               $regexp = new Regexp($value['url']);
               $value['url'] = $regexp->RemoveUrlParam('?');
               $suffix = strrchr($value['url'],'.');

               $dir = DIRECTORY_SEPARATOR.'video'.DIRECTORY_SEPARATOR.substr(md5(time()),8,16).$suffix;
               try {
                  $VideoEdit = new VideoEdit($value['url'],$this->path.$dir);
                  $VideoEdit->SaveLinkVideo();
               } catch (\Exception $e) {

               }


               $value['status'] = 1;
               $value['path'] = $dir;
               $data['video'][$key] = $value;
               $this->QueueData($data,'set');
               return '['.date('h:i:sa').'] $Video->status('.$value['status'].')->count('.count($IsStatus).')'.'->'.$key.' = '.$dir;
            }
         }
         /**
         * @if 保存 img 文件
         **/
         if (!empty($data['img'])) {
            // 判断 status = 0
            $IsStatus = array_filter($data['img'], function($isArr) {
               return $isArr['status'] == '0';
            });
            if (!empty($IsStatus)) {
               $key = array_keys($IsStatus)[0];
               $value = $IsStatus[array_keys($IsStatus)[0]];

               $dir = DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR;
               $SaveLinkImage = $UploadImage->SaveLinkImage(
                  $value['url'],
                  $this->path.$dir,
                  [],
                  true,
                  false,
                  false
               );

               if ($SaveLinkImage['error'] != 0) {
                  return 'Save Link Image Error '.$SaveLinkImage['error'];
               }

               $dir = $dir.$SaveLinkImage['path']['relative'];

               $value['status'] = 1;
               $value['path'] = $dir;
               $data['img'][$key] = $value;
               $this->QueueData($data,'set');
               return '['.date('h:i:sa').'] $Img->status('.$value['status'].')->count('.count($IsStatus).')'.'->'.$key.' = '.$dir;
            }
         }

         /**
         * @if 所有 html 开始替换资源
         **/
         if (!empty($data['a'])) {
            // 判断 status = 0
            $IsStatus = array_filter($data['a'], function($isArr) {
               return $isArr['status'] == '1';
            });

            if (!empty($IsStatus)) {
               $key = array_keys($IsStatus)[0];
               $value = $IsStatus[$key];



               $path = $this->RootPath.$value['path'];
               $html = file_get_contents($path);

               if (empty($html)) {
                  $file->delete();
                  unset($data['a'][$key]);
                  $this->QueueData($data,'set');
                  return '['.date('h:i:sa').']New File Unset Keys';
               }

               $html = $this->tools->ReolaceDirLink($data,$html);

               /**
               * @name 创建 .html
               * @author
               * @version
               **/
               $FileWriter = new FileWriter($path,'w+');
               $FileWriter->write($html);

               $value['status'] = 2;
               $data['a'][$key] = $value;
               $this->QueueData($data,'set');
               return '['.date('h:i:sa').'] $A->status('.$value['status'].')->count('.count($IsStatus).')'.'->'.$key.' = '.$value['path'];
            }
         }

         /**
         * @if 所有 css 中的图片链接 开始替换资源
         **/
         if (!empty($data['css'])) {
            // 判断 status = 0
            $IsStatus = array_filter($data['css'], function($isArr) {
               return $isArr['status'] == '1';
            });

            if (!empty($IsStatus)) {
               $key = array_keys($IsStatus)[0];
               $value = $IsStatus[$key];


               $path = $this->RootPath.$value['path'];
               $css = file_get_contents($path);

               if (empty($css)) {
                  $file->delete();
                  unset($data['a'][$key]);
                  $this->QueueData($data,'set');
                  return '['.date('h:i:sa').']New File Unset Keys';
               }

               $css = $this->tools->ReolaceDirImgLink($data,$css);

               /**
               * @name 创建 .html
               * @author
               * @version
               **/
               $FileWriter = new FileWriter($path,'w+');
               $FileWriter->write($css);

               $value['status'] = 2;
               $data['css'][$key] = $value;
               $this->QueueData($data,'set');
               return '['.date('h:i:sa').'] $A->status('.$value['status'].')->count('.count($IsStatus).')'.'->'.$key.' = '.$value['path'];
            }
         }

         /**
         * @if 所有 js 中的图片链接 开始替换资源
         **/

         if (!empty($data['js'])) {
            // 判断 status = 0
            $IsStatus = array_filter($data['js'], function($isArr) {
               return $isArr['status'] == '1';
            });

            if (!empty($IsStatus)) {
               $key = array_keys($IsStatus)[0];
               $value = $IsStatus[$key];


               $path = $this->RootPath.$value['path'];
               $js = file_get_contents($path);

               if (empty($js)) {
                  $file->delete();
                  unset($data['a'][$key]);
                  $this->QueueData($data,'set');
                  return '['.date('h:i:sa').']New File Unset Keys';
               }

               $js = $this->tools->ReolaceDirImgLink($data,$js);

               /**
               * @name 创建 .html
               * @author
               * @version
               **/


               $FileWriter = new FileWriter($path,'w+');
               $FileWriter->write($js);

               $value['status'] = 2;
               $data['js'][$key] = $value;
               $this->QueueData($data,'set');
               return '['.date('h:i:sa').'] $A->status('.$value['status'].')->count('.count($IsStatus).')'.'->'.$key.' = '.$value['path'];
            }
         }

         return '['.date('h:i:sa').']New File No Data';
      } catch (\Exception $e) {
         return $e;
      }


   }
}
