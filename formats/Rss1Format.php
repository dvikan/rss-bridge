<?php

class Rss1Format extends FormatAbstract
{
    const MIME_TYPE = 'application/rss+xml';
    public function stringify()
    {
        $extraInfos = $this->getExtraInfos();
        return render_template('rss1.html.php', [
            'feed_url' => get_current_url(),
            'title' => $extraInfos['name'] ?? '(No title)',
            'description' => $extraInfos['name'] ?? '(No description)',
            'link' => $extraInfos['uri'] ?? REPOSITORY,
            'items' => $this->getItems(),
        ]);
    }
}
