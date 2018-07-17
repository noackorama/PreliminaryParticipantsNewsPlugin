<?php

class PreliminaryParticipantsNewsPlugin extends StudipPlugin implements SystemPlugin
{
    function __construct()
    {
        parent::__construct();
        if (!$GLOBALS['perm']->have_perm('admin')
            && (match_route('dispatch.php/my_courses') || match_route('dispatch.php/course/details'))) {
            $user_id = $GLOBALS['user']->id;
            $my_obj  = array();
            $_my_obj  = array();
            $db = DBManager::get()->prepare("SELECT seminar_id AS object_id
                        FROM admission_seminar_user
                        WHERE user_id = '$user_id'
                        AND status IN ('accepted', 'awaiting')");
            $db->execute();
            $courses = $db->fetchAll(PDO::FETCH_COLUMN);
            foreach ($courses as $course) {
                $nav = MyRealmModel::checkOverview($_my_obj, $user_id, $course);
                if (!is_null($nav)) {
                    $img = $nav->getImage();
                    if(!empty($img)) {
                        $my_obj[$course]["image"] = $img instanceOf Icon ? $img->asImagePath() : $img['src'];
                        $my_obj[$course]["msg"] = $img instanceOf Icon ? $img->getAttributes()['title'] : $img['title'];
                    }
                }
            }
            if (match_route('dispatch.php/my_courses')) {
                $courses        = json_encode($my_obj);
                $current_course = 'null';
            } else if (in_array(Request::option('sem_id'), array_keys($my_obj))) {
                $current_course = json_encode($my_obj[Request::option('sem_id')]);
                $courses        = '{}';
                $titel          = _("Ankündigungen");
                $content_box    = addcslashes($this->render_news_contentbox(Request::option('sem_id')), "'\n\r");
            }

            $script = <<<EOT
jQuery('document').ready(function(){
    jQuery.each($courses, function(id,data) {
       jQuery('a[href*="sem_id=' + id + '"]').after('<div style="float:right"><a data-dialog href="' + STUDIP.ABSOLUTE_URI_STUDIP + 'plugins.php/PreliminaryParticipantsNewsPlugin/get_news_dialog/' + id +'"><img style="cursor: pointer" width="20" height="20" src="' + data.image +  '" /></a></div>');
    });
    var cc = $current_course;
    if (cc) {
        jQuery('article.studip').first().before('$content_box');
    }
});
EOT;
            if (count($my_obj) &&
                (match_route('dispatch.php/my_courses')
                    || in_array(Request::option('sem_id'), array_keys($my_obj)))
            ) {
                PageLayout::addHeadElement('script', array('type' => 'text/javascript'), $script);
            }
        }
    }

    function get_news_dialog_action($id = null)
    {
        if (!$id || preg_match('/[^\\w,-]/', $id)) {
            throw new Exception('wrong parameter');
        }
        echo $this->render_news_contentbox($id);
        echo "<script>
        if (jQuery('footer section.comments form').attr('action')) {
            var newhref = STUDIP.ABSOLUTE_URI_STUDIP + 'plugins.php/PreliminaryParticipantsNewsPlugin/get_news_dialog/' + '$id' + jQuery('footer section.comments form').attr('action');
        } else {
            var newhref = STUDIP.ABSOLUTE_URI_STUDIP + 'plugins.php/PreliminaryParticipantsNewsPlugin/get_news_dialog/' + '$id' + jQuery('footer section.comments a').attr('href');
        }
        jQuery('footer section.comments a').attr('data-dialog', 1);
        jQuery('footer section.comments a').attr('href', newhref);
        jQuery('footer section.comments form').attr('action', newhref);
        jQuery('footer section.comments form').attr('data-dialog', 1);
        </script>";
    }

    function render_news_contentbox($range_id)
    {

        // Check if user wrote a comment
        if (Request::submitted('accept') && trim(Request::get('comment_content')) && Request::isPost()) {
            CSRFProtection::verifySecurityToken();
            StudipComment::create(array(
            'object_id' => Request::get('comsubmit'),
            'user_id' => $GLOBALS['user']->id,
            'content' => trim(Request::get('comment_content'))
            ));
        }

        $perm = false;
        $news = StudipNews::GetNewsByRange($range_id, false, true);
        $count_all_news  = count($news);
        $rss_id = Config::get()->NEWS_RSS_EXPORT_ENABLE ? StudipNews::GetRssIdFromRangeId($range_id) : false;
        $range = $range_id;
        $tf = new Flexi_TemplateFactory($GLOBALS['STUDIP_BASE_PATH'] . '/app/views');
        $template = $tf->open('news/display.php');
        URLHelper::addLinkParam('sem_id', $range_id);
        $ret =  $template->render(compact('perm','news','count_all_news','rss_id','range'));
        URLHelper::removeLinkParam('sem_id');
        return $ret;
    }
}
