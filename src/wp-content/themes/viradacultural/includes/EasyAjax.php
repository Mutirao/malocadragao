<?php
/**
 * Description of EasyAjax
 *
 * @author rafael
 */

class EasyAjax {
    static $admin = array();

    static function init(){
        $methods = get_class_methods(__CLASS__);

        foreach($methods as $method){
            if($method != 'init')
                if(in_array($method, self::$admin)){
                    add_action("wp_ajax_$method", array(__CLASS__,$method));
                }else{
                    add_action("wp_ajax_$method", array(__CLASS__,$method));
                    add_action("wp_ajax_nopriv_$method", array(__CLASS__,$method));
                }
        }
    }

    /**
     * usado no metodo form::cidade_uf_autocomplete()
     */
    static function get_ibge_cidade_uf(){
        global $wpdb;

        $value = $_REQUEST['value'];
        $value = preg_replace('/([^\/]+)\/.*/', '', $value);

        $vals = $wpdb->get_results("
            SELECT
                ibge_cidades.id as cidade_id,
                ibge_cidades.nome as cidade_nome,
                ibge_ufs.id as uf_id,
                ibge_ufs.sigla as uf_sigla,
                ibge_ufs.nome as uf_nome
            FROM
                ibge_cidades,
                ibge_ufs
            WHERE
                ibge_cidades.nome LIKE '{$value}%' AND
                ibge_ufs.id = ibge_cidades.ufid
            ORDER BY
                ibge_cidades.nome ASC,
                ibge_ufs.sigla ASC
        ");
        $result = array('keys'=>array());
        foreach($vals as $val){
            $result['keys'][] = "{$val->cidade_nome} / {$val->uf_sigla}";
            $result["{$val->cidade_nome} / {$val->uf_sigla}"] = $val;
        }
        echo json_encode($result);
        die;
    }

	static function FORAget_nasredes_posts() {

        global $wpdb;

        $last_id = $_POST['last_id'];
        $what = $_POST['what'];

        $queryEnd = $what == 'newer' ? '> %d' : '< %d ORDER BY ID DESC LIMIT 50';

		$posts = $wpdb->get_col( $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_type IN ('twitter_cpt', 'instagram_cpt') AND post_status = 'publish' AND ID " . $queryEnd, $last_id));

        if (sizeof($posts) < 1)
            die;

		$query = new WP_Query(array(
			'post_type' => array('instagram_cpt', 'twitter_cpt'),
			'post__in' => $posts,
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'DESC'
		));

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                html::part('loop-redes', array('ajaxhide' => $what == 'newer'));
            }
        }

		die;

	}

    static function get_nasredes_posts() {

        include ('Simple-Database-PHP-Class/Db.php');
        include ('extra-db-config.php');
        $db = new Db('mysql',
            $db_config['virada_nas_redes']['host'],
            $db_config['virada_nas_redes']['name'],
            $db_config['virada_nas_redes']['user'],
            $db_config['virada_nas_redes']['pass']
        );

        $last_id = $_POST['last_id'];
        $what = $_POST['what'];

        $queryEnd = $what == 'newer' ? '> :last_id' : '< :last_id ORDER BY ID DESC LIMIT 50';

        $items = $db->query( 'SELECT id FROM items WHERE id ', array( 'last_id' => $last_id ) );

        if ($items->count()){
            while ($item = $items->fetch()) {
                $dateCreated = date_create($item->date);
                $item->dateTimeFormatted = date_format($dateCreated, 'd-m-Y - H:i');
                $item->dateFormatted = date_format($dateCreated, 'Y-m-d');
                $ajaxhide = $what == 'newer';
                include '../wp-content/themes/viradacultural/parts/loop-redes.php';
            }
        }

		die;

	}
}

EasyAjax::init();

?>
