<?php

namespace Mydnic\Kanpen\Services;

use Mydnic\Kanpen\Models\CampaignDelivery;

class TrackingUrlService
{
    public function openPixelUrl(CampaignDelivery $send): string
    {
        return route(config('kanpen.routes.tracking_open_name', 'kanpen.tracking.open'), ['token' => $send->token]);
    }

    public function clickProxyUrl(CampaignDelivery $send, string $originalUrl): string
    {
        return route(config('kanpen.routes.tracking_click_name', 'kanpen.tracking.click'), [
            'token' => $send->token,
            'url' => base64_encode($originalUrl),
        ]);
    }

    public function rewriteLinks(string $html, CampaignDelivery $send): string
    {
        if (! config('kanpen.tracking.click', true)) {
            return $html;
        }

        return preg_replace_callback(
            '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i',
            function (array $matches) use ($send) {
                $original = $matches[1];

                // Skip mailto: and anchor links
                if (str_starts_with($original, 'mailto:') || str_starts_with($original, '#')) {
                    return $matches[0];
                }

                $tracked = $this->clickProxyUrl($send, $original);

                return str_replace($original, $tracked, $matches[0]);
            },
            $html
        );
    }

    public function injectTrackingPixel(string $html, CampaignDelivery $send): string
    {
        if (! config('kanpen.tracking.open', true)) {
            return $html;
        }

        $pixelUrl = $this->openPixelUrl($send);
        $pixel = sprintf(
            '<img src="%s" width="1" height="1" border="0" alt="" style="display:none;" />',
            $pixelUrl
        );

        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $pixel.'</body>', $html);
        }

        return $html.$pixel;
    }

    public function processHtml(string $html, CampaignDelivery $send): string
    {
        $html = $this->rewriteLinks($html, $send);
        $html = $this->injectTrackingPixel($html, $send);

        return $html;
    }
}
