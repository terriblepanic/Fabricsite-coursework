<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/auth_check.php';
require_once __DIR__.'/includes/header.php';

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}

$fabricId=(int)($_GET['id']??0);
$userId=$_SESSION['user']['id'];

$f=$pdo->prepare('SELECT * FROM fabrics WHERE id=? AND available=1');$f->execute([$fabricId]);
$fabric=$f->fetch(PDO::FETCH_ASSOC);
if(!$fabric){echo'<p>Ткань не найдена</p>';require_once __DIR__.'/includes/footer.php';exit;}

$success=false;$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    try{
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO requests (user_id,status_id) VALUES (?,1)')->execute([$userId]);
        $reqId=$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO request_items (request_id,fabric_id) VALUES (?,?)')
            ->execute([$reqId,$fabricId]);
        $pdo->commit(); $success=true;
    }catch(PDOException $e){$pdo->rollBack();$error=$e->getMessage();}
}
?>
<h2>Оформление заявки</h2>
<p><strong><?=h($fabric['name'])?></strong> — <?=h($fabric['price_rub'])?> ₽</p>

<?php if($success):?>
    <p class="success">Заявка отправлена!</p>
    <p><a href="index.php">Вернуться в каталог</a></p>
<?php else:?>
    <?php if($error):?><p class="errors"><?=h($error)?></p><?php endif;?>
    <form method="post"><?php csrf_field();?>
        <button type="submit">Подтвердить заявку</button>
    </form>
<?php endif;?>
<?php require_once __DIR__.'/includes/footer.php'; ?>
