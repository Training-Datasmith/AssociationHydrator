<?php

declare(strict_types=1);

/**
 * Example: Eager-load Doctrine associations to avoid N+1 queries.
 *
 * Given a collection of Order entities, this example loads all related
 * `items` and `items.product` associations with two bulk queries instead
 * of one query per order.
 */

use Sylius_Labs\Association_Hydrator\Association_Hydrator;

// Obtain these from your DI container / service locator.
/** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
$entityManager = get_entity_manager();

$orderRepository = $entityManager->getRepository(\App\Entity\Order::class);
$classMetadata   = $entityManager->getClassMetadata(\App\Entity\Order::class);

$hydrator = new Association_Hydrator($entityManager, $classMetadata);

// Load a list of orders (only IDs and scalars are fetched at this point).
$orders = $orderRepository->findBy(['status' => 'pending']);

// Hydrate multiple association paths in one call.
// Dot notation traverses nested associations.
$hydrator->hydrate_associations($orders, [
    'items',           // load all OrderItem entities for each Order
    'items.product',   // then load the Product for each OrderItem
]);

// Now iterate without triggering additional queries.
foreach ($orders as $order) {
    foreach ($order->getItems() as $item) {
        echo $item->getProduct()->getName() . PHP_EOL;
    }
}
