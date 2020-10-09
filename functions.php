<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
require_once 'libs/contents.php';
require_once 'libs/options.php';
require_once 'libs/comments.php';
require_once 'libs/utils.php';
require_once 'libs/pageNav.php';
require_once 'libs/DbFunc.php';
/**
 * 注册文章解析 hook
 * From AlanDecode(https://imalan.cn)
 */
Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('contents', 'parseContent');
Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('contents', 'parseContent');
/**
 * 后台编辑按钮
 */
Typecho_Plugin::factory('admin/write-post.php')->bottom = array('utils', 'addButton');
Typecho_Plugin::factory('admin/write-page.php')->bottom = array('utils', 'addButton');
/**
 * 评论接口 by 染念
 */
Typecho_Plugin::factory('Widget_Abstract_Comments')->contentEx = array('comments', 'parseContent');
Typecho_Plugin::factory('Widget_Feedback')->comment = array('comments', 'insertSecret');
/**
 * 文章与独立页自定义字段
 * @param Typecho_Widget_Helper_Layout $layout
 */
function themeFields(Typecho_Widget_Helper_Layout $layout)
{
    if (preg_match("/write-post.php/", $_SERVER['REQUEST_URI'])) {
        $articleType = new Typecho_Widget_Helper_Form_Element_Select('articleType', array(
            'default' => '默认',
            'link' => '纯链接'
        ), 'articletype', _t('文章类型'), _t('见wiki说明'));
        $layout->addItem($articleType);

        $banner = new Typecho_Widget_Helper_Form_Element_Text('banner', NULL, NULL, _t('文章头图'), _t('输入一个图片 url，作为缩略图显示在文章列表，没有则不显示'));
        $layout->addItem($banner);
        $excerpt = new Typecho_Widget_Helper_Form_Element_Text('excerpt', NULL, NULL, _t('文章摘要'), _t('输入一段文本来自定义摘要，如果为空则自动提取文章前 70 字。'));
        $layout->addItem($excerpt);


    }
}

function themeInit($archive)
{
    //评论回复楼层最高999层.这个正常设置最高只有7层
    Helper::options()->commentsMaxNestingLevels = 999;
    //强制评论关闭反垃圾保护
    Helper::options()->commentsAntiSpam = false;
    //  点赞
    if ($archive->request->isPost() && $archive->request->agree) {
        if ($archive->request->agree == $archive->cid) {
            exit(utils::agree($archive->cid));
        } elseif ($archive->is('index')) {
            exit(utils::agree($archive->request->agree));
        }
        exit('error');
    }
    // follow
    if ($archive->request->isPost() && $archive->request->follow){
        if ($archive->request->follow == 'follow'){
            exit(DbFunc::addFollow($archive->request->uid,$archive->request->fid));
        }elseif ($archive->request->follow == 'unfollow'){
            exit(DbFunc::cancleFollow($archive->request->uid,$archive->request->fid));
        }elseif ($archive->request->follow == 'status'){
            exit(DbFunc::statusFollow($archive->request->uid,$archive->request->fid));
        }
        exit('error');
    }
    // 初始化数据库设置
    DbFunc::init();
}


function get_user_group($name = NULL)
{
    $db = Typecho_Db::get();
    if ($name === NULL)
        $profile = $db->fetchRow($db->select('group', 'uid')->from('table.users')->where('uid = ?', intval(Typecho_Cookie::get('__typecho_uid'))));
    else
        $profile = $db->fetchRow($db->select('group', 'name', 'screenName')->from('table.users')->where('name=? OR screenName=?', $name, $name));
    return $profile['group'];
}

function get_comment($coid)
{
    $db = Typecho_Db::get();
    return $db->fetchRow($db->select()
        ->from('table.comments')
        ->where('coid = ?', $coid)
        ->limit(1));
}

/**
 * 获取文章中的9宫格图片
 * @param $archive
 * @return mixed|string
 */
function getPostImg($archive)
{
    $img = array();
    //  匹配 img 的 src 的正则表达式
    preg_match_all("/<img.*?src=\"(.*?)\".*?\/?>/i", $archive->content, $img);
    //  判断是否匹配到图片
    if (count($img) > 0 && count($img[0]) > 0) {
        //  返回图片
        if (count($img) > 9) {
            return array_slice($img, 0, 9);
        }
        return $img[1];
    } else {
        //  如果没有匹配到就返回 none
        return array();
    }
}

/**
 * 获取页面内容的第一个URL
 * @param $content
 * @return mixed
 */
function parseFirstURL($content)
{
    $preg = '/(https?|ssh|ftp):\/\/[^\s"]+/';
    preg_match_all($preg, $content, $arr);
    return $arr[0][0];
}

function getV2exAvatar($obj)
{
    $mail = $obj->author->mail ? md5($obj->author->mail) : '';
    return "//cdn.v2ex.com/gravatar/" . $mail . "?s=32&d=mp";
}

function getUserV2exAvatar($mail_)
{
    $mail = $mail_ ? md5($mail_) : '';
    return "//cdn.v2ex.com/gravatar/" . $mail . "?s=32&d=mp";
}
/**
 * markdown parse
 * @param $str
 * @param int $num
 * @return false|string
 */
function parseMarkdownBeforeText($str,$num=15){
    $pattern = '/(.*?)\[[\s\S]*?\]\([\s\S]*?\)/';
    preg_match($pattern, $str, $match);
    if (count($match) > 0 ){
        return substr($match[1],0,$num);
    }else{
        return substr($str,0,$num);
    }
}

/**
 * @param $str
 * @param int $num
 * @return false|string
 */
function parseMarkdownInText($str,$num=15){
    $pattern = '/\[([\s\S]*?)\]\([\s\S]*?\)/';
    preg_match($pattern, $str, $match);
    if (count($match) > 0 ){
        return substr($match[1],0,$num);
    }else{
        return substr($str,0,$num);
    }
}

/**
 * 获取所有分类
 * 为了可以获得头像，将数据写在 description 中，首先写入头像网址格式<网址>，然后写入简介
 *
 * mid：分类id
 * name：分类名称
 * slug：分类缩写名
 * type：分类类型，譬如categorery
 * description：分类的描述
 * count：该分类下的文章数目
 * order：
 * parent：父分类的mid
 * levels：所在的层级
 * directory：Array类型，数组元素是每层分类的slug
 * permalink：该分类的url
 * feedUrl：该分类的feed地址
 * feedRssUrl：该分类的feedRss地址
 * feedAtomUrl：该分类的feedAtom地址
 * @param $obj
 * @param int $cnt
 * @return array
 */
function getCategories($obj, $cnt = -1)
{
    $categories = $obj->widget('Widget_Metas_Category_List');
    $arr = array();
    $preg = '/^<(.*)>([\s\S]*)/';
    if ($categories->have()) {
        $i = 0;
        while ($categories->next()) {
            if ($cnt > 0 && $i >= $cnt) {
                break;
            }
            $tmp = array();
            preg_match_all($preg, $categories->description, $res); // res[0][0] 是匹配的整个串 [1] 是网址 [2]是内容
            if (isset($res[1][0])) { // has img
                $imgurl = $res[1][0];
                $desc = $res[2][0];
            } else {
                $imgurl = "https://www.easyicon.net/api/resizeApi.php?id=1171951&size=128";
                $desc = $categories->description;
            }

            array_push($tmp, $categories->mid, $categories->name, $categories->permalink, $imgurl, $desc, $categories->count);
            array_push($arr, $tmp);
            $i = $i + 1;
        }
    }

    return $arr;

}


/**
 * 解析分类描述中的图片，格式<imgurl>
 * @param $desc
 * @return mixed|string
 */
function parseDesc2img($desc)
{
    $preg = '/^<(.*)>([\s\S]*)/';
    preg_match_all($preg, $desc, $res);
    if (isset($res[1][0])) {
        return $res[1][0];
    }
    return "";
}

function parseDesc2text($desc)
{
    $preg = '/^<(.*)>([\s\S]*)/';
    preg_match_all($preg, $desc, $res);
    if (isset($res[2][0])) {
        return $res[2][0];
    }
    return $desc;
}

/**
 * 时间友好化
 *
 * @access public
 * @param mixed
 * @return
 */
function formatTime($time)
{
    $text = '';
    $time = intval($time);
    $ctime = time();
    $t = $ctime - $time; //时间差
    if ($t < 0) {
        return date('Y-m-d', $time);
    }
    $y = date('Y', $ctime) - date('Y', $time);//是否跨年
    switch ($t) {
        case $t == 0:
            $text = '刚刚';
            break;
        case $t < 60://一分钟内
            $text = $t . '秒前';
            break;
        case $t < 3600://一小时内
            $text = floor($t / 60) . '分钟前';
            break;
        case $t < 86400://一天内
            $text = floor($t / 3600) . '小时前'; // 一天内
            break;
        case $t < 2592000://30天内
            if ($time > strtotime(date('Ymd', strtotime("-1 day")))) {
                $text = '昨天';
            } elseif ($time > strtotime(date('Ymd', strtotime("-2 days")))) {
                $text = '前天';
            } else {
                $text = floor($t / 86400) . '天前';
            }
            break;
        case $t < 31536000 && $y == 0://一年内 不跨年
            $m = date('m', $ctime) - date('m', $time) - 1;
            if ($m == 0) {
                $text = floor($t / 86400) . '天前';
            } else {
                $text = $m . '个月前';
            }
            break;
        case $t < 31536000 && $y > 0://一年内 跨年
            $text = (11 - date('m', $time) + date('m', $ctime)) . '个月前';
            break;
        default:
            $text = (date('Y', $ctime) - date('Y', $time)) . '年前';
            break;
    }
    return $text;
}

function theme_random_posts()
{
    $defaults = array(
        'number' => 6,
        'before' => '<ul class="archive-posts">',
        'after' => '</ul>',
        'xformat' => '<li class="archive-post"> <a class="archive-post-title" href="{permalink}">{title}</a>
 </li>'
    );
    $db = Typecho_Db::get();
    $sql = $db->select()->from('table.contents')
        ->where('status = ?', 'publish')
        ->where('type = ?', 'post')
        ->limit($defaults['number'])
        ->order('RAND()');
    $result = $db->fetchAll($sql);
    echo $defaults['before'];
    foreach ($result as $val) {
        $val = Typecho_Widget::widget('Widget_Abstract_Contents')->filter($val);
        echo str_replace(array('{permalink}', '{title}'), array($val['permalink'], $val['title']), $defaults['xformat']);
    }
    echo $defaults['after'];
}

/**
 * 显示下一篇
 *
 * @access public
 * @return void
 */
function theNext($widget)
{
    $db = Typecho_Db::get();
    $sql = $db->select()->from('table.contents')
        ->where('table.contents.created > ?', $widget->created)
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.type = ?', $widget->type)
        ->where('table.contents.password IS NULL')
        ->order('table.contents.created', Typecho_Db::SORT_ASC)
        ->limit(1);
    $content = $db->fetchRow($sql);

    if ($content) {
        $content = $widget->filter($content);
        $link = '<a href="' . $content['permalink'] . '" target="_self">
                        <div class="button">
                            <div class="label btn1">下一篇</div>
                            <div class="title" title="' . $content['title'] . '">' . $content['title'] . '</div>
                        </div>
                    </a>';
        echo $link;
    } else {
        echo '<div class="button btn2 off">
                  <div class="label">下一篇</div>
                  <div class="title">没有更多了</div>
              </div>';
    }
}

/**
 * 显示上一篇
 *
 * @access public
 * @return void
 */
function thePrev($widget)
{
    $db = Typecho_Db::get();
    $sql = $db->select()->from('table.contents')
        ->where('table.contents.created < ?', $widget->created)
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.type = ?', $widget->type)
        ->where('table.contents.password IS NULL')
        ->order('table.contents.created', Typecho_Db::SORT_DESC)
        ->limit(1);
    $content = $db->fetchRow($sql);
    if ($content) {
        $content = $widget->filter($content);
        $link = '<a href="' . $content['permalink'] . '" target="_self">
                        <div class="button">
                            <div class="label btn1">上一篇</div>
                            <div class="title" title="' . $content['title'] . '">' . $content['title'] . '</div>
                        </div>
                    </a>';
        echo $link;
    } else {
        echo '<div class="button btn2 off">
                  <div class="label">上一篇</div>
                  <div class="title">没有更多了</div>
              </div>';
    }
}



/**
 * 获取主题版本号
 */
function themeVersion()
{
    $info = Typecho_Plugin::parseInfo(__DIR__ . '/index.php');
    return $info['version'];
}