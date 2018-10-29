<?php
class area {

    public function __action()
    {
        //装企区域分布
        list($areaList,$drilldownList) = $this->_getAreaList();
        $this->list  = json_encode($areaList);
        $this->drilldownList = json_encode($drilldownList);;
    }

    /**
     * 获取区域城市的装企数量
     *
     * @return array
     */
    private function _getAreaList()
    {
        $list = $drilldownList = array();
        //每个城市的装企数量
        $sites = sitesModel::sites();
        if (!empty($sites['opensite']))
        {
            $siteIdArr = array_keys($sites['opensite']);
            $siteIds = implode(',',$siteIdArr);
            $siteList = $this->_getCountBySiteId($siteIds);
            $list = $this->_getSiteNameAndNum($sites,$siteList);
            //旺铺数量
            $cdtStr = 'property > 0 ';
            $vipList = $this->_getCountBySiteId($siteIds,$cdtStr,true);
            //普通数量
            $normalList = $this->_getNormalList($siteList,$vipList);
            $drilldownList = $this->_getSiteNameAndNum($sites,$vipList,$normalList,true);
            //获取装修公司前50位的城市
            $top = !empty($_GET['top'])? $_GET['top']:'';
            $list = $this->_getTopNSiteData($list,$top);

        }
        return array($list,$drilldownList);
    }

    /**
     * 获取普通装修会员数量的列表
     *
     * @param $siteList
     * @param $vipList
     * @return array
     */
    private function _getNormalList($siteList,$vipList)
    {
        $normalList = array();
        if (!empty($siteList))
        {
            foreach ($siteList as $k=>$v)
            {
                $existKey = array_key_exists($k,$vipList);
                if (!empty($existKey))
                {
                    $normalList[$k] = $v - $vipList[$k];
                }
            }
        }
        return $normalList;
    }

    /**
     * 获取排序前N位的城市站点
     *
     * @param $data
     * @param int $n
     * @return array
     */
    private function _getTopNSiteData($data ,$n = '')
    {
        $numArr = $newData = array();
        if (!empty($data))
        {
            foreach ($data as $k=>$v)
            {
                $numArr[$k] = $v['y'];
            }
            //按照装修公司数量重新排序
            array_multisort($numArr,SORT_NUMERIC,SORT_DESC,$data);
            $totalNum = count($data);
            $newData = $data;
            if (!empty($n))
            {
                $n = $n > $totalNum?$totalNum:$n;
                $newData = array_slice($data, 0, $n);
            }
        }
        return $newData;
    }

    /**
     * 获取站点城市名称和数量
     *
     * @param $sites
     * @param $siteList
     * @return array
     */
    private function _getSiteNameAndNum($sites,$siteList,$normalList = array(),$isDrilldown = false)
    {
        $newList = array();
        if(!empty($sites['id2sitename']))
        {
            $id2sitename = $sites['id2sitename'];
            $i=0;
            foreach ($siteList as $k=>$v)
            {
                $existKey = array_key_exists($k,$id2sitename);
                if (!empty($existKey))
                {
                    if ($isDrilldown == true)
                    {
                        if (!empty($normalList))
                        {
                            //拼接成hchart格式: name=> 城市 id=> 城市 data = array( array('旺铺',旺铺数量),array('普通店铺',普通店铺数量))
                            $newList[$i]['name'] = $id2sitename[$k];
                            $newList[$i]['id'] =  $id2sitename[$k];
                            $vipNum = intval($siteList[$k]);
                            $normalNum = intval($normalList[$k]);
                            $newList[$i]['data'] = array(
                                array('旺铺',$vipNum),array('普通店铺',$normalNum)
                            );
                        }
                    }
                    else
                    {
                        //拼接成hchart格式: name=> 城市 y=> 数量 drilldown=>下钻ID
                        $newList[$i]['name'] = $id2sitename[$k];
                        $newList[$i]['y'] =  intval($siteList[$k]);
                        $newList[$i]['drilldown'] = $id2sitename[$k];
                    }
                }
                $i++;
            }
        }
        return $newList;
    }

    /***
     * 获取每个site站点下的装修公司数量
     *
     * @param $siteIds
     * @return mixed
     * 返回值格式: $newList = ['站点ID'=>公司数量];
     */
    private function _getCountBySiteId($siteIds,$cdtStr = '',$returnAllSiteId = false)
    {
        $cdt = array('siteID'=>$siteIds);
        $newList =  $siteId2NumList = array();
        $groupBy = 'siteID';
        $orderBy = 'num DESC';
        $fields ='siteID';
        $numList = decorationsModel::getCount($cdt,$cdtStr,false,$fields,$groupBy);
        if (!empty($numList))
        {
            foreach ($numList as $v)
            {
                $newList[$v['siteID']] = $v['num'];
            }
            if ($returnAllSiteId == true)
            {
                $newList = $this->_getAllSiteCount($siteIds,$newList);
            }
            ksort($newList);
        }
        return $newList;
    }

    /**
     * 获取指定城市站点的旺铺信息(包含数量为0)
     *
     * @param $siteIds
     * @param $siteId2NumList
     * @return array
     */
    private function _getAllSiteCount($siteIds,$siteId2NumList)
    {
        $newList = array();
        $siteIdArr = explode(',',$siteIds);
        $keySiteId = array_keys($siteId2NumList);
        foreach ($siteIdArr as $v)
        {
            //判断siteid是否在$siteId2NumList里
            $newList[$v] = in_array($v,$keySiteId)?$siteId2NumList[$v]:0;
        }
        return $newList;
    }

    /**
     * 获取所包含的省份信息
     *
     * @param $siteIds
     */
    private  function  _getProvince($siteIds)
    {
        $provinceIdArr = array();
        $cdt = array('id' => $siteIds);
        $fields = 'id,provinceID';
        $list = sitesModel::getALLsites($cdt, $fields);
        if (!empty($list))
        {
            foreach ($list as $k => $v)
            {
                $provinceIdArr[$v['provinceID']][] = $v['id'];
            }
        }
        return $provinceIdArr;
    }
}