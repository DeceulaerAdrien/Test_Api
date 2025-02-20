<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;

$app = \Slim\Factory\AppFactory::create();

# ===================== JSON Database =====================

$app->get('/json/items', function ($request, $response, $args) {

    $items = file_get_contents('db.json');

    $decodedItems = json_decode($items);

    $response->getBody()->write(json_encode($decodedItems));

    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/json/newitems', function ($request, $response, $args) {
    $data = $request->getParsedBody();

    $items = file_get_contents('db.json');
    $decodedItems = json_decode($items, true);

    $newItem = [
        'item_id' => count($decodedItems['items']) + 1,
        'item_name' => $data['item_name'],
        'item_price' => $data['item_price']
    ];

    $decodedItems['items'][] = $newItem;

    file_put_contents('db.json', json_encode($decodedItems, JSON_PRETTY_PRINT));

    $response->getBody()->write(json_encode($newItem));

    return $response->withHeader('Content-Type', 'application/json')
        ->withStatus(201);
});



# ===================== MySql Database =====================


class MySql
{

    private $user;
    private $password;
    private $database;
    private $host;
    private $pdo;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        $this->user = $_ENV['DB_USER'];
        $this->password = $_ENV['DB_PASSWORD'];
        $this->database = $_ENV['DB_DATABASE'];
        $this->host = $_ENV['DB_HOST'];

        $this->connect();
    }

    private function connect()
    {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->database}", $this->user, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Connexion réussie à la base de données!";
        } catch (PDOException $e) {
            echo "Erreur de connexion à MySQL: " . $e->getMessage();
        }
    }

    public function getItems()
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM items");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Erreur lors de la récupération des éléments: " . $e->getMessage();
        } finally {
            $this->pdo = null;
        }
    }

    public function addItem($itemName, $itemPrice)
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO items (item_name, item_price) VALUES (:item_name, :item_price)");
            $stmt->bindParam(':item_name', $itemName);
            $stmt->bindParam(':item_price', $itemPrice);
            $stmt->execute();
            echo "Nouvel élément ajouté avec succès!";
        } catch (PDOException $e) {
            echo "Erreur lors de l'ajout de l'élément: " . $e->getMessage();
        } finally {
            $this->pdo = null;
        }
    }
}

$app->get('/sql/items', function ($request, $response, $args) {
    $db = new MySQL();
    $items = $db->getItems();


    $response->getBody()->write(json_encode($items));
    return $response->withHeader('Content-Type', 'application/json');
});


$app->post('/sql/newitems', function ($request, $response, $args) {

    $data = $request->getParsedBody();

    $db = new MySQL();
    $db->addItem($data['item_name'], $data['item_price']);

    $response->getBody()->write(json_encode(["message" => "Item ajouté avec succès!"]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

$app->run();
