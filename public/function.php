<?php
ini_set('log_errors', 'on');
ini_set('error_log', 'php.log');

$debug_flg = false;
function debug($str)
{
    global $debug_flg;
    if ($debug_flg) {
        error_log('デバッグ:' . $str);
    }
}

//================================
// セッション準備・セッション有効期限を延ばす
//================================
//セッションファイルの置き場を変更する
session_save_path("/var/tmp/");
//ガーベージコレクションが削除するセッションの有効期限を設定（30日以上経っているものに対してだけ１００分の１の確率で削除）
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30);
//ブラウザを閉じても削除されないようにクッキー自体の有効期限を延ばす
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30);
//セッションを使う($_SESSION変数は、他のページに移動した時にその移動前の$_SESSIONの中身を保持できる)
session_start();
//現在のセッションIDを新しく生成したものと置き換える（なりすましのセキュリティ対策）
session_regenerate_id();

// ============================
// 画面表示処理開始ログ吐き出し関数
// ============================
function debugLogStart()
{
    debug('//////////////////画面表示処理開始/////////////////////');
    debug('セッションID:' . session_id());
    debug('セッション変数の中身:' . print_r($_SESSION, true));
    debug('現在日時タイムスタンプ:' . time());
    if (!empty($_SESSION['login_date']) && !empty($_SESSION['login_limit'])) {
        debug('ログイン期限日時タイムスタンプ:' . ($_SESSION['login_date'] + $_SESSION['login_limit']));
    }
}

//================================
// グローバル変数
//================================
//エラーメッセージ格納用の配列
$err_msg = array();

//================================
// 定数
//================================
//エラーメッセージを定数に設定
define('MSG01', '入力必須です');
define('MSG02', '200文字以内で入力してください');
define('MSG03', '6文字以上で入力してください');
define('MSG04', 'emailの形式で入力してください');
define('MSG05', '半角英数字で入力してください');
define('MSG06', 'パスワードが合っていません');
define('MSG07', 'emailが既に登録してあります');
define('MSG08', 'エラーが発生しました。しばらく経ってからやり直してください。');
define('MSG09', 'emailまたはパスワードが違います');
define('MSG10', '古いパスワードが違います');
define('MSG11', '古いパスワードと同じです');
define('MSG12', '文字で入力してください');
define('MSG13', '正しくありません');
define('MSG14', '有効期限切れです');
define('MSG15', '選択してください');
define('MSG16', 'ゲストユーザーのアドレスのためこの機能はご利用できません');
define('SUC01', 'パスワードを変更しました');
define('SUC02', 'メールを送信しました');
define('SUC03', 'パスワードを再発行しました');
define('SUC04', '金子峻大 特設サイトへようこそ！！');
define('SUC05', 'プロフィールを変更しました');
define('SUC06', '投稿しました');
define('SUC07', '投稿が削除されました');
define('SUC08', '投稿詳細が編集されました');
define('SUC09', '処理に成功しました。（ゲストユーザーのため反映はされていません。）');


//ゲストユーザーのIDとemailを定義
$gestUserId = 33;
$gestUserEmail = 'guest@login.com';

//==========================================
//DB
//==========================================
//DB接続関数
function dbConnect()
{
    $db = parse_url($_SERVER['CLEARDB_DATABASE_URL']);
    $db['dbname'] = ltrim($db['path'], '/');
    $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset=utf8";
    $user = $db['user'];
    $password = $db['pass'];
    $options = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY =>true,
  );
  $dbh = new PDO($dsn,$user,$password,$options);
  return $dbh;
        
    // $dsn = 'mysql:dbname=special_site;host=localhost;charset=utf8';
    // $user = 'root';
    // $pass = 'root';
    // $option = array(
    //     // SQL実行失敗時にはエラーコードのみ設定
    //     PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING,
    //     // デフォルトフェッチモードを連想配列形式に設定
    //     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    //     // バッファードクエリを使う(一度に結果セットをすべて取得し、サーバー負荷を軽減)
    //     // SELECTで得た結果に対してもrowCountメソッドを使えるようにする
    //     PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    // );
    // $dbh = new PDO($dsn, $user, $pass, $option);
    // return $dbh;
    }
// SQL実行関数
function queryPost($dbh, $sql, $data)
{
    $stmt = $dbh->prepare($sql);
    if (!$stmt->execute($data)) {
        debug('クエリ失敗');
        $err_msg['common'] = MSG08;
        return false;
    } else {
        debug('クエリ成功');
        return $stmt;
    }

    return $stmt;
}
//==========================================
//バリデーション
//==========================================
// 未入力チェック
function validRequired($str, $key)
{
    if (empty($str)) {
        global $err_msg;
        $err_msg[$key] = MSG01;
    }
}
// 最大文字数
function maxlen($str, $key, $len = 255)
{
    if (mb_strlen($str) > $len) {
        global $err_msg;
        $err_msg[$key] = MSG02;
    }
}
// 最小文字数
function minLen($str, $key, $len = 6)
{
    if (mb_strlen($str) < $len) {
        global $err_msg;
        $err_msg[$key] = MSG03;
    }
}
// emailの形式チェック
function validEmail($str, $key)
{
    if (!preg_match("/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/", $str)) {
        global $err_msg;
        $err_msg[$key] = MSG04;
    }
}
// 半角チェック
function validHalf($str, $key)
{
    if (!preg_match("/^[a-zA-Z0-9]+$/", $str)) {
        global $err_msg;
        $err_msg[$key] = MSG05;
    }
}
//　パスワード照合
function validMatch($str1, $str2, $key)
{
    if ($str1 !== $str2) {
        global $err_msg;
        $err_msg[$key] = MSG06;
    }
}
// パスワード
function validPass($str, $key)
{
    validHalf($str, $key);
    maxLen($str, $key);
    minLen($str, $key);
}
// セレクトボックス
function validSelect($str, $key)
{
    if (!preg_match("/^[1-9]+$/", $str)) {
        global $err_msg;
        $err_msg[$key] = MSG15;
    }
}
// email重複チェック
function validEmailDup($str)
{
    global $err_msg;

    try {
        $dbh = dbConnect();
        $sql = 'SELECT count(*) FROM users WHERE email=? AND delete_flg = 0';
        $data = array($str);
        $stmt = queryPost($dbh, $sql, $data);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty(array_shift($result))) {
            global $err_msg;
            $err_msg['email'] = MSG07;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
        $err_msg['common'] = MSG08;
    }
}
// フォーム入力保持
function keep($key)
{
    if (!empty($_POST[$key])) {
        return $_POST[$key];
    }
}
//ゲストユーザーのEmailかチェック
function validGestUserEmail($str, $key)
{
    global $gestUserEmail;
    if ($gestUserEmail == $str) {
        global $err_msg;
        $err_msg[$key] = MSG16;
        debug('ゲストユーザのアドレスが入力されました。');
    }
}

//==========================================
//データ取得
//==========================================
//ユーザー情報取得
function getUser($str)
{
    try {
        $dbh = dbConnect();
        $sql = 'SELECT * FROM users WHERE id=? AND delete_flg = 0';
        $data = array($str);
        $stmt = queryPost($dbh, $sql, $data);
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ユーザー情報取得
function getUserOne($u_id)
{
    debug('ユーザー情報を取得');
    debug('ユーザーID' . $u_id);
    try {
        $dbh = dbConnect();
        $sql = 'SELECT u.id, u.username, u.job, u.hobby, u.area, u.intro, u.pic, u.create_date, u.update_date, a.name AS a_name FROM users AS u INNER JOIN area AS a ON u.area = a.id WHERE u.id=? AND u.delete_flg=0';
        $data = array($u_id);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}

// フォーム入力保持
function getFormData($str, $flg = false)
{
    global $dbData;
    global $err_msg;
    if ($flg) {
        $method = $_GET;
    } else {
        $method = $_POST;
    }
    // ユーザー情報がある場合
    if (!empty($dbData)) {
        // フォームのエラーがある場合
        if (!empty($err_msg[$str])) {
            // postに情報がある場合
            if (isset($method[$str])) {
                return $method[$str];
            } else {
                return $dbData[$str];
            }
        } else {
            // postに情報がありDBの情報が違う
            if (isset($method[$str]) && $method[$str] !== $dbData[$str]) {
                return $method[$str];
            } else {
                // そもそも変更していない
                return $dbData[$str];
            }
        }
    } else {
        if (isset($method[$str])) {
            return $method[$str];
        }
    }
}
//画像表示
function showImg($path)
{
    if (empty($path)) {
        return 'img/sample-img.png';
    } else {
        return $path;
    }
}
function showProfImg($str)
{
    if (empty($str)) {
        return 'img/avatar-unknown.png';
    }
    return $str;
}

function areaData()
{
    try {
        $dbh = dbConnect();
        $sql = 'SELECT * FROM area';
        $data = array();
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
            return $stmt->fetchAll();
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}

function getCate()
{
    try {
        $dbh = dbConnect();
        $sql = 'SELECT * FROM category';
        $data = array();
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
            return $stmt->fetchAll();
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}


// 固定長チェック
function validLength($str, $key, $len = 8)
{
    if (mb_strlen($str) !== $len) {
        global $err_msg;
        $err_msg[$key] = $len . MSG12;
    }
}

// 投稿情報取得
function getPost($u_id, $a_id)
{
    try {
        $dbh = dbConnect();
        $sql = 'SELECT * FROM article WHERE posted_id = ? AND id=? AND delete_flg = 0';
        $data = array($u_id, $a_id);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
        $err_msg['common'] = MSG08;
    }
}

// 検索された投稿情報を取得
function getPostList($nowMin = 1, $category, $sort, $span = 20)
{
    debug('投稿情報を取得します。');
    // 例外処理
    try {
        // DBへ接続
        $dbh = dbConnect();
        // 【〜件数用】 のSQL文作成
        $sql = 'SELECT id FROM article';
        if (!empty($category)) $sql .= ' WHERE category_id = ' . $category;
        if (!empty($sort)) {
            switch ($sort) {
                case 1: //      1の場合は昇順 ↓
                    $sql .= ' ORDER BY create_date DESC';
                    break;
                case 2: //      2の場合は降順 ↓
                    $sql .= ' ORDER BY create_date ASC';
                    break;
            }
        }
        $data = array();
        $stmt = queryPost($dbh, $sql, $data);

        $rst['total'] = $stmt->rowCount();
        $rst['total_page'] = ceil($rst['total'] / $span); //総ページ数 総レコード数 ÷ 20件

        if (!$stmt) {
            return false;
        }

        // 【ページング用】 のSQL文作成
        $sql = 'SELECT * FROM article';
        if (!empty($category)) $sql .= ' WHERE category_id = ' . $category;
        if (!empty($sort)) {
            switch ($sort) {
                case 1:
                    $sql .= ' ORDER BY create_date DESC';
                    break;
                case 2:
                    $sql .= ' ORDER BY create_date ASC';
                    break;
            }
        }
        $sql .= ' LIMIT ' . $span . ' OFFSET ' . $nowMin;
        $data = array();
        debug('SQL:' . $sql);
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':span', $span, PDO::PARAM_INT);
        $stmt->bindParam(':nowMin', $nowMin, PDO::PARAM_INT);
        if ($stmt->execute()) {
            $rst['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rst;
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}

// 投稿情報
function getPostOne($a_id)
{
    debug('投稿詳細を取得します');
    debug('写真ID:' . $a_id);
    try {
        $dbh = dbConnect();
        $sql = 'SELECT a.id, a.title, a.key1, a.key2, a.key3, a.category_id, a.comment, a.pic1, a.pic2, a.pic3, a.pic4, a.create_date, a.update_date, a.posted_id, u.username, u.intro, u.pic, c.name AS c_name FROM article AS a INNER JOIN users AS u ON a.posted_id = u.id INNER JOIN category AS c ON a.category_id = c.id WHERE a.id=? AND a.delete_flg = 0 AND u.delete_flg = 0';
        $data = array($a_id);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}
// 各投稿のコメントを取得
function getPartnerComment($a_id) {
    debug('コメント取得します');
    try {
        $dbh = dbConnect();
        $sql = 'SELECT pc.from_user, pc.comment, u.id, u.username, u.pic FROM partnerComment AS pc INNER JOIN users AS u ON pc.from_user = u.id WHERE article_id = ? AND pc.delete_flg = 0 AND u.delete_flg = 0 ORDER BY pc.send_date ASC';
        $data = array($a_id);

        $stmt = queryPost($dbh, $sql, $data);
        if($stmt) {
            return $stmt->fetchAll();
        }else{
            return false;
        }

    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}
//掲示板情報の取得
function getBordData($m_id)
{
    debug('掲示板情報を取得します');
    try {
        $dbh = dbConnect();
        $sql = 'SELECT sender_id, receiver_id, article_id, create_date FROM bord WHERE id = :m_id AND delete_flg = 0';
        $data = array(':m_id' => $m_id);

        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生：' . $e->getMessage());
    }
}
// 指定された写真情報メッセージ取得
function getMsgData($id)
{
    debug('写真ID:' . $id);
    try {
        $dbh = dbConnect();
        $sql = 'SELECT m.from_user, m.msg, u.pic FROM message AS m INNER JOIN users AS u ON m.from_user=u.id WHERE m.article_id = ? AND m.delete_flg = 0';
        $data = array($id);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
            return $stmt->fetchAll();
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}
//指定された掲示板のメッセージ情報を取得
function getMsgAndBord($m_id)
{
    debug('メッセージ情報を取得します');
    try {
        $dbh = dbConnect();
        $sql = 'SELECT m.id AS m_id, b.article_id, bord_id, msg, from_user, to_user, send_date, b.sender_id, b.receiver_id, b.create_date FROM message AS m RIGHT JOIN bord AS b ON m.bord_id=b.id WHERE b.id=? ORDER BY send_date ASC';
        $data = array($m_id);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
            return $stmt->fetchAll();
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}

// 掲示板ごとに取るのでforeachで廻して表示する
function getMyMsgsAndBord($u_id)
{
    debug('掲示板情報とmsg情報を取得します');
    try {
        $dbh = dbConnect();
        $sql = 'SELECT id, sender_id, receiver_id FROM bord WHERE sender_id = :u_id OR receiver_id = :u_id AND delete_flg = 0 ORDER BY create_date DESC';
        $data = array(':u_id' => $u_id);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
            $result = $stmt->fetchAll();
            foreach ($result as $key => $val) {
                $sql = 'SELECT * FROM message WHERE bord_id = :b_id AND delete_flg = 0 ORDER BY send_date DESC';
                $data = array(':b_id' => $val['id']);
                $stmt = queryPost($dbh, $sql, $data);
                if ($stmt) {
                    $result[$key]['msg'] = $stmt->fetchAll();
                }
            }
            return $result;
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}


// 自分情報取得
function myData($u_id)
{
    try {
        $dbh = dbConnect();
        $sql = 'SELECT * FROM article WHERE posted_id=? AND delete_flg = 0';
        $data = array($u_id);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
            return $stmt->fetchAll();
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}
// 自分のお気に入り情報取得
function myLike($u_id)
{
    try {
        $dbh = dbConnect();
        $sql = 'SELECT * FROM favorite AS f LEFT JOIN article AS a ON f.article_id = a.id WHERE f.user_id = ? AND f.delete_flg = 0 AND a.delete_flg = 0';
        $data = array($u_id);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
            return $stmt->fetchAll();
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}

// 自分のフォロー情報取得
function myFollow($u_id)
{
    try {
        $dbh = dbConnect();
        $sql = 'SELECT * FROM follow AS fo LEFT JOIN users AS u ON fo.opponent_id = u.id WHERE fo.user_id = ? AND fo.delete_flg = 0 AND u.delete_flg = 0';
        $data = array($u_id);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
            return $stmt->fetchAll();
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}

//==========================================
//メッセージ
//==========================================
// エラーメッセージを取得
function errmsg($key)
{
    global $err_msg;
    if (!empty($err_msg[$key])) {
        return $err_msg[$key];
    }
}
// セッションを１回だけ取得
function getFlash($key)
{
    if (!empty($_SESSION[$key])) {
        $data = $_SESSION[$key];
        $_SESSION[$key] = '';
        return $data;
    }
}
//==========================================
//その他
//==========================================
// サニタイズ
function sanitize($str)
{
    return htmlspecialchars($str, ENT_QUOTES);
}

// アップロード
function uploadImg($file, $key)
{
    debug('画像アップロード');
    debug('ファイル情報:' . print_r($_FILES, true));
    if (isset($file['error']) && is_int($file['error'])) {
        try {
            switch ($file['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_NO_FILE:
                    throw new RuntimeException('ファイルが選択されていません');
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    throw new RuntimeException('ファイルサイズが大きすぎます');
                default:
                    throw new RuntimeException('その他のエラーが発生しました');
            }

            //画像の形式を判別
            $type = @exif_imagetype($file['tmp_name']);
            if (!in_array($type, [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                throw new RuntimeException('画像形式が未対応です');
            }

            $path = 'uploads/' . sha1_file($file['tmp_name']) . image_type_to_extension($type);
            if (!move_uploaded_file($file['tmp_name'], $path)) {
                throw new RuntimeException('ファイル保存時にエラーが発生しました');
            }

            //保存したファイルのパスの権限を変更
            chmod($path, 0644);

            debug('ファイルは正常にアップロードされました');
            debug('ファイルパス:' . $path);
            return $path;
        } catch (RuntimeException $e) {
            global $err_msg;
            debug($e->getMessage());
            $err_msg[$key] = $e->getMessage();
        }
    }
}

// お気に入り
function isLike($u_id, $a_id)
{
    try {
        $dbh = dbConnect();
        $sql = 'SELECT * FROM favorite WHERE user_id=? AND article_id=?';
        $data = array($u_id, $a_id);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt->rowCount()) {
            debug('お気に入りです');
            return true;
        } else {
            debug('特に気に入ってません');
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}

// メール送信
function sendMail($to, $subject, $comment, $from)
{
    if (!empty($to) && !empty($subject) && !empty($comment)) {
        //文字化けしないように
        mb_language('Japanese');
        mb_internal_encoding("UTF-8");

        $result = mb_send_mail($to, $subject, $comment, "From:" . $from);
        if ($result) {
            debug('メール送信成功');
        } else {
            debug('メール送信失敗');
        }
    }
}

// 認証キー発行
function makeRand($length = 8)
{
    $char = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJLKMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; ++$i) {
        $str .= $char[mt_rand(0, 61)];
    }
    return $str;
}

// GETパラメータの付与
function appendGet($del_key = array())
{
    if (!empty($_GET)) {
        $str = '?';
        foreach ($_GET as $key => $val) {
            if (!in_array($key, $del_key, true)) {
                $str .= $key . '=' . $val . '&';
            }
        }
        $str = mb_substr($str, 0, -1, "UTF-8");
        return $str;
    }
}

// フォロー
function isFollow($u_id, $o_id)
{
    try {
        $dbh = dbConnect();
        $sql = 'SELECT * FROM follow WHERE user_id=? AND opponent_id=?';
        $data = array($u_id, $o_id);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt->rowCount()) {
            debug('フォロー中');
            return true;
        } else {
            debug('フォローしてない');
            return false;
        }
    } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
    }
}
