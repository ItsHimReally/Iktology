<div class="sideBar">
    <div class="sB_list">
        <?php $m = mysqli_query($link, "SELECT * FROM `dbs`") ?>
        <?php while ($q = mysqli_fetch_array($m)): ?>
            <div class="sB_db">
                <div class="sB_db-title">
                    <img src="images/database.svg" alt="DB">
                    <span><?=$q['name']?></span>
                </div>
                <span class="sB_db-span"><?=$q['fileName']?></span>
                <span class="sB_db-span"><?=$q['records']?> записей</span>
                <span class="sB_db-span"><?=$q['timestamp']?></span>
            </div>
        <?php endwhile; ?>
    </div>
    <a class="sB_button" href="#">
        <img src="images/database-add.svg" alt="DB">
        <span>Добавить базу</span>
    </a>
</div>