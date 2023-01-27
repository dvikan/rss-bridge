<?php
/** @var FeedItem[] $items */
?>
<?xml version="1.0"?>

<rdf:RDF
    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
    xmlns="http://purl.org/rss/1.0/">

    <channel rdf:about="<?= e($feed_url) ?>">
        <title><?= e($title) ?></title>
        <link><?= e($link) ?></link>

        <description>
            <?= e($description) ?>
        </description>

        <items>
            <rdf:Seq>
                <?php foreach ($items as $item): ?>
                    <rdf:li resource="<?= e($item->getURI()) ?>" />
                <?php endforeach; ?>
            </rdf:Seq>
        </items>
    </channel>

    <?php foreach ($items as $item): ?>
        <item rdf:about="<?= e($item->getURI()) ?>">
            <title><?= e($item->getTitle()) ?></title>
            <link><?= e($item->getURI()) ?></link>
            <description>
                <?= e($item->getContent()) ?>
            </description>
        </item>
    <?php endforeach; ?>

</rdf:RDF>
