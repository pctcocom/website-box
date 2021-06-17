<?php
namespace Pctco\WebsiteBox;
use Pctco\Verification\Regexp;
/**
* 处理数据工具
**/
class Tools{
   function __construct($query){
      $this->query = $query;
   }
   /**
   * @name Relatively PathTo Url
   * @describe 将相对路径组成link
   * @param mixed $url
   * @return String URL
   **/
   public function RelativelyPathToUrl($url){
      $regexp = new Regexp($url);
      $UrlType = $regexp->IsUrlType();
      if ($UrlType === 'RelativelyPathUrl') {
         return $this->query->domain.$url;
      }
      return $url;
   }
   /**
   * @name Url Sort Out
   * @describe url整理
   * @param mixed $Arr
   * @return String URL
   **/
   public function UrlSortOut($Arr){
      /*
      * $group  key 原有链接   value 新链接
      */
      $group = [];
      foreach (array_unique(array_filter($Arr)) as $UrlLink) {
         // 判断是否是站内链接
         $ToUrlLink = $this->RelativelyPathToUrl($UrlLink);
         if(strpos($ToUrlLink, $this->query->domain) === 0){
            $regexp = new Regexp($ToUrlLink);
            $NewUrlLink = $regexp->RemoveUrlParam('#');
            $group[$UrlLink] = [
               'url'   =>   $NewUrlLink,
               // 存储路径
               'path'   =>   '',
               // status: 0 = 未保存,1 = 已保存html,2 = 已替换资源链接
               'status'   =>   0
            ];
         }
      }
      return $group;
   }

   /**
   * @name a link path
   * @describe a标签链接存储路径
   * @param mixed $data = [img=>[],link=>[],....] 所有数据
   * @return String
   **/
   public function ReolaceDirLink($data,$html){
      $ApostropheGroup = $PathGroup = [];
      foreach ($data as $k => $v) {
         foreach ($v as $k2 => $v2) {
            if ($k2 === '/') {
               $ApostropheGroup[] = '"'.$k2.'"';
               $PathGroup[] = '".'.$v2['path'].'"';
            }else{
               $ApostropheGroup[] = '"'.$k2.'';
               $PathGroup[] = '"./..'.$v2['path'].'';
            }

         }
      }
      return str_replace($ApostropheGroup,$PathGroup,$html);
   }
   public function ReolaceDirImgLink($data,$html){
      $ApostropheGroup = $PathGroup = [];
      foreach ($data['img'] as $k2 => $v2) {
         $ApostropheGroup[] = '"'.$k2.'';
         $PathGroup[] = '"'.$v2['path'].'';
      }
      return str_replace($ApostropheGroup,$PathGroup,$html);
   }
   public function specialchars($html){
      return str_replace(['&amp;'],['&'],$html);
   }
}
