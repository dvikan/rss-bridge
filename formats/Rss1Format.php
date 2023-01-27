<?php

class Rss1Format extends FormatAbstract
{
    const MIME_TYPE = 'application/rss+xml';
    public function stringify()
    {
        $extraInfos = $this->getExtraInfos();
        return render_template('rss1.html.php', [
            'feed_url' => get_current_url(),
            'title' => $extraInfos['name'],
            'description' => $extraInfos['name'],
            'link' => $extraInfos['uri'],
            'items' => $this->getItems(),
        ]);
    }
}
