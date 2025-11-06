<div class="row">
    <div class="col">
        <div class="card my-2 intra__stats-card intra__stats-users">
            <div class="card-body">
                <h5 class="card-title">Registrierte Benutzer</h5>
                <p class="card-text display-4">
                    <?php
                    // Fetch the number of users from the database
                    $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM intra_users");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo htmlspecialchars($result['user_count']);
                    ?>
                </p>
                <i class="fa-solid fa-user-tie"></i>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card my-2 intra__stats-card intra__stats-workers">
            <div class="card-body">
                <h5 class="card-title">Angelegte Mitarbeiter</h5>
                <p class="card-text display-4">
                    <?php
                    // Fetch the number of users from the database
                    $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM intra_mitarbeiter");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo htmlspecialchars($result['user_count']);
                    ?>
                </p>
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card my-2 intra__stats-card intra__stats-enotf">
            <div class="card-body">
                <h5 class="card-title">eNOTF-Protokolle</h5>
                <p class="card-text display-4">
                    <?php
                    // Fetch the number of users from the database
                    $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM intra_edivi");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo htmlspecialchars($result['user_count']);
                    ?>
                </p>
                <i class="fa-solid fa-house-medical-flag"></i>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card my-2 intra__stats-card intra__stats-documents">
            <div class="card-body">
                <h5 class="card-title">Erstellte Dokumente</h5>
                <p class="card-text display-4">
                    <?php
                    // Fetch the number of users from the database
                    $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM intra_mitarbeiter_dokumente");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo htmlspecialchars($result['user_count']);
                    ?>
                </p>
                <i class="fa-solid fa-folder-open"></i>
            </div>
        </div>
    </div>
</div>

<?php
