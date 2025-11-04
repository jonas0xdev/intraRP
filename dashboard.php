<?php
require_once __DIR__ . '/assets/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php
  $SITE_TITLE = 'Dashboard';
  include __DIR__ . '/assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" id="dashboard" class="container-full position-relative">
  <div class="container-full mx-5">
    <div class="row mt-3">
      <div class="col-4 mx-auto text-center">
        <img src="<?php echo SYSTEM_LOGO ?>" alt="<?php echo SYSTEM_NAME ?>" style="height:128px;width:auto">
      </div>
    </div>

    <div class="row">
      <div class="col" id="cards">
        <?php
        require __DIR__ . '/assets/config/database.php';
        $stmt = $pdo->prepare("SELECT * FROM intra_dashboard_categories ORDER BY priority ASC");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $row) {
          $stmt2 = $pdo->prepare("SELECT * FROM intra_dashboard_tiles WHERE category = :category_id ORDER BY priority ASC");
          $stmt2->bindParam(':category_id', $row['id']);
          $stmt2->execute();
          $result2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        ?>
          <div class="mb-5">
            <div class="row">
              <div class="col mb-3">
                <h2><?= $row['title'] ?></h2>
              </div>
            </div>

            <?php
            $chunkedTiles = array_chunk($result2, 6);
            foreach ($chunkedTiles as $tileRow) {
            ?>
              <div class="row mb-3">
                <?php foreach ($tileRow as $tile) { ?>
                  <div class="col-md-2"> <!-- 12 / 6 = 2 per tile -->
                    <a href="<?= $tile['url'] ?>">
                      <div class="card h-100">
                        <div class="card-body">
                          <div class="card-fa mb-3 text-center d-block">
                            <i class="<?= $tile['icon'] ?>"></i>
                          </div>
                          <h5 class="card-title text-center fw-bold">
                            <?= $tile['title'] ?>
                          </h5>
                        </div>
                      </div>
                    </a>
                  </div>
                <?php } ?>
              </div>
            <?php } ?>
          </div>
        <?php }
        if ($stmt->rowCount() == 0) {
          echo '<div class="alert alert-warning" role="alert">Es wurde noch kein Dashboard konfiguriert. Bitte konfiguriere dein Dashboard in der <a class="fw-bold link-underline" href="' . BASE_PATH . 'settings/dashboard/index.php">Administrationsoberfläche</a>.</div>';
        } ?>
      </div>
    </div>

  </div>
  <?php include __DIR__ . "/assets/components/footer.php"; ?>
</body>

</html>