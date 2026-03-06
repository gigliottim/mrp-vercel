<?php

/**
 * Ver estructura de companies en BD auth
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database\DatabaseManager;

echo "<h2>Estructura de Companies y Databases</h2>";
echo "<hr>";

try {
    $conn = DatabaseManager::connection('mrp_auth');

    // Ver todas las companies
    echo "<h3>Companies</h3>";
    $stmt = $conn->query("SELECT * FROM companies");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($companies);
    echo "</pre>";

    // Ver company_databases
    echo "<h3>Company Databases</h3>";
    $stmt = $conn->query("SELECT * FROM company_databases");
    $databases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($databases);
    echo "</pre>";

    // Ver user_company
    echo "<h3>User-Company Relations</h3>";
    $stmt = $conn->query("SELECT * FROM user_company");
    $relations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($relations);
    echo "</pre>";
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error</h3>";
    echo "<pre>";
    echo $e->getMessage();
    echo "</pre>";
}
