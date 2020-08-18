<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
	// ログインしている
	$_SESSION['time'] = time();

	$members = $db->prepare('SELECT * FROM members WHERE id=?');
	$members->execute(array($_SESSION['id']));
	$member = $members->fetch();
} else {
	// ログインしていない
	header('Location: login.php'); exit();
}

// 投稿を記録する
if (!empty($_POST)) {
	if ($_POST['message'] != '') {
		$message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
		$message->execute(array(
			$member['id'],
			$_POST['message'],
			$_POST['reply_post_id']
		));

		header('Location: index.php'); exit();
	}
}

// 投稿を取得する
$page = $_REQUEST['page'];
if ($page == '') {
	$page = 1;
}
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;
$start = max(0, $start);

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

// 返信の場合
if (isset($_REQUEST['res'])) {
	$response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
	$response->execute(array($_REQUEST['res']));

	$table = $response->fetch();
	$message = '@' . $table['name'] . ' ' . $table['message'];
}

// リツイート機能
if (isset($_REQUEST['retweet'])) {
	$retweets = $db->prepare('SELECT id, message, member_id, retweet_id, retweet_member_id FROM posts WHERE id=? ORDER BY created DESC');
	$retweets->execute(array($_REQUEST['retweet']));
	$retweet = $retweets->fetch();

	$retweetCalculates = $db->prepare('SELECT COUNT(*) AS cal FROM posts WHERE retweet_id=? AND retweet_member_id=?');
	if ((int)$retweet['retweet_id'] === 0) {// 投稿が大元の投稿である場合
	$retweetCalculates->execute(array($retweet['id'], $member['id']));
	$retweetCalculate = $retweetCalculates->fetch();
	// ↑ログイン中のメンバーがその投稿をリツイートした数の抽出
	} elseif ((int)$retweet['retweet_id'] !== 0) {// 投稿がリツイートの投稿である場合
	$retweetCalculates->execute(array($retweet['retweet_id'], $member['id']));
	$retweetCalculate = $retweetCalculates->fetch();
	// ↑ログイン中のメンバーがその投稿をリツイートした数の抽出
	}

	if ((int)$retweetCalculate['cal'] === 0) {// ログイン中のユーザーがまだリツイートしていなかった場合
		$tweet = $db->prepare('INSERT INTO posts SET message=?, member_id=?, reply_post_id=0, retweet_id=?, retweet_member_id=?, created=NOW()');
		if((int)$retweet['retweet_id'] === 0) {// 投稿が大元の投稿である場合
			$tweet->execute(array(
				$retweet['message'],
				$retweet['member_id'],
				$retweet['id'],
				$member['id'],
			));
			// ↑リツイートの投稿をpostテーブルに挿入(retweet_idは投稿のid)
		} elseif ((int)$retweet['retweet_id'] !== 0) {// 投稿が誰かのリツイートである場合
			$tweet->execute(array(
				$retweet['message'],
				$retweet['member_id'],
				$retweet['retweet_id'],
				$member['id'],
			));
			// ↑リツイートの投稿をpostテーブルに挿入（retweet_idは投稿のretweet_id）
		}
	} elseif ((int)$retweetCalculate['cal'] === 1) {// ログイン中のユーザーが既に投稿をリツイートしていた場合
		$delete = $db->prepare('DELETE FROM posts WHERE retweet_id=? AND retweet_member_id=?');
		if ((int)$retweet['retweet_id'] === 0) {// 投稿が大元の投稿である場合
			$delete->execute(array($retweet['id'], $member['id']));
			// retweet_idが大元もidになっている投稿を削除
		} elseif ((int)$retweet['retweet_id'] !== 0) {// 投稿が自分のリツイートだった場合
			$delete->execute(array($retweet['retweet_id'], $member['id']));
			// 自分のリツイートを削除
		}
	}
	header('Location:index.php'); exit();
}

// いいね機能
if (isset($_REQUEST['like'])) {
	$likes = $db->prepare('SELECT id, message, member_id, retweet_id, retweet_member_id FROM posts WHERE id=? ORDER BY created DESC');
	$likes->execute(array($_REQUEST['like']));
	$like = $likes->fetch();
	
	$likeCalculates = $db->prepare('SELECT COUNT(*) AS cal FROM likes WHERE member_id=? AND post_id=?');
	if ((int)$like['retweet_id'] === 0) {// 投稿が大元の投稿である場合
		$likeCalculates->execute(array($member['id'], $like['id']));
		$likeCalculate = $likeCalculates->fetch();
		// ↑ログイン中のユーザーが投稿をいいねした数を抽出
	} elseif((int)$like['retweet_id'] !== 0) {// 投稿がリツイートの投稿だった場合
		$likeCalculates->execute(array($member['id'], $like['retweet_id']));
		$likeCalculate = $likeCalculates->fetch();
		// ↑ログイン中のユーザーが大元の投稿をいいねした数を抽出
	}

	if ((int)$likeCalculate['cal'] === 0){// いいねしていなかった場合
		$good = $db->prepare('INSERT INTO likes SET member_id=?, post_id=?, created=NOW()');
		if((int)$like['retweet_id'] === 0){// 投稿が大元の投稿である場合
			$good->execute(array(
				$member['id'],
				$like['id'],
			));
			// ↑いいね情報をlikesテーブルに追加(post_idは投稿のid)
		} elseif ((int)$like['retweet_id'] !== 0) {// 投稿がリツイートだった場合
			$good->execute(array(
				$member['id'],
				$like['retweet_id'],
			));
			// ↑いいね情報をlikesテーブルに追加(post_idは投稿のretweet_id)
		}
	} elseif ((int)$likeCalculate['cal'] === 1) {// 投稿に既にいいねしていた場合
		$delete = $db->prepare('DELETE FROM likes WHERE member_id=? AND post_id=?');
		if((int)$like['retweet_id'] === 0) {// 投稿が大元の投稿である場合
			$delete -> execute(array($member['id'], $like['id']));
		} elseif((int)$like['retweet_id'] !== 0) {// 投稿がリツイートだった場合
			$delete->execute(array($member['id'], $like['retweet_id']));
		}
	}
	header('Location:index.php'); exit();
}

// htmlspecialcharsのショートカット
function h($value) {
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// 本文内のURLにリンクを設定します
function makeLink($value) {
	return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>' , $value);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>ひとこと掲示板</title>

	<link rel="stylesheet" href="style.css" />
</head>

<body>
<div id="wrap">
  <div id="head">
    <h1>ひとこと掲示板</h1>
  </div>
  <div id="content">
  	<div style="text-align: right"><a href="logout.php">ログアウト</a></div>
    <form action="" method="post">
      <dl>
        <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
        <dd>
          <textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
          <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
        </dd>
      </dl>
      <div>
        <p>
          <input type="submit" value="投稿する" />
        </p>
      </div>
    </form>

<?php
foreach ($posts as $post):
?>
    <div class="msg">
    <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
		<?php if ((int)$post['retweet_member_id'] !== 0) {// 投稿がリツイートだった場合
			$retweet_members = $db->prepare('SELECT name FROM members WHERE id=?');
			$retweet_members->execute(array($post['retweet_member_id']));
			$retweet_member = $retweet_members->fetch();
		?>
		<p class="day" style="font-size:13px"><?php echo h($retweet_member['name']);?>がリツイートしました</p>
		<!-- ↑誰がリツイートしたか表示 -->
		<?php } ?>
    <p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>[<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>
    <p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
		<?php
if ($post['reply_post_id'] > 0):
?>
<a href="view.php?id=<?php echo
h($post['reply_post_id']); ?>">
返信元のメッセージ</a>
<?php
endif;
?>
<?php
if ($_SESSION['id'] == $post['member_id']):
?>
[<a href="delete.php?id=<?php echo h($post['id']); ?>"
style="color: #F33;">削除</a>]
<?php
endif;
?>
<?php 
// リツイート数のカウント
$retweetCounts = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE retweet_id=?');
if ($post['retweet_id'] == 0) {// 投稿が大元の投稿である場合
	$retweetCounts->execute(array($post['id']));
	$retweetCount = $retweetCounts->fetch();
} else {// 投稿がリツイートである場合
	$retweetCounts->execute(array($post['retweet_id']));
	$retweetCount = $retweetCounts->fetch();
}
?>
			<a href="index.php?retweet=<?php echo h($post['id']);?>" style="text-decoration:none;margin: 0 10px">&crarr;
			<span><?php echo $retweetCount['cnt'];?></span></a>
<?php 
// いいね数のカウント
$likeCounts = $db->prepare('SELECT COUNT(*) AS cnt FROM likes WHERE post_id=?');
if ($post['retweet_id'] == 0) {// 投稿が大元の投稿である場合
	$likeCounts->execute(array($post['id']));
	$likeCount = $likeCounts->fetch();
} else {// 投稿がリツイートである場合
	$likeCounts->execute(array($post['retweet_id']));
	$likeCount = $likeCounts->fetch();
}

$user_likeCounts = $db->prepare('SELECT COUNT(*) AS cnt FROM likes WHERE member_id=? AND post_id=?');
if ($post['retweet_id'] == 0) {// 投稿が大元の投稿である場合
	$user_likeCounts->execute(array($member['id'], $post['id']));
	$user_likeCount = $user_likeCounts->fetch();
	// ↑ログイン中のユーザーがその投稿をいいねした数を抽出
} else {
	// 投稿がリツイートである場合
	$user_likeCounts->execute(array($member['id'], $post['retweet_id']));
	$user_likeCount = $user_likeCounts->fetch();
	// ↑ログイン中のユーザーがその投稿をいいねした数を抽出
}

if ($user_likeCount['cnt'] == 0) {// ログイン中のユーザーがその投稿をいいねしていない場合
?>
			<a href="index.php?like=<?php echo h($post['id']);?>" style="text-decoration:none">&hearts;
			<span><?php print($likeCount['cnt']);?></span></a>
<?php 
} elseif ($user_likeCount['cnt'] == 1) {// ログイン中のユーザーがその投稿をいいねしている場合
?>
			<a href="index.php?like=<?php echo h($post['id']);?>" style="color:red;text-decoration:none">&hearts;
			<!-- ハートマークを赤色に変更 -->
			<span><?php print($likeCount['cnt']);?></span></a>
<?php } ?>
    </p>
    </div>
<?php
endforeach;
?>

<ul class="paging">
<?php
if ($page > 1) {
?>
<li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
<?php
} else {
?>
<li>前のページへ</li>
<?php
}
?>
<?php
if ($page < $maxPage) {
?>
<li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
<?php
} else {
?>
<li>次のページへ</li>
<?php
}
?>
</ul>
  </div>
</div>
</body>
</html>
