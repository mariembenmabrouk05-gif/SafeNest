<?php
// api/auto_category.php

function autoCategorizeUrl($url, $title = '') {
    if (empty($url) && empty($title)) return 'Divers';
    
    $text_to_search = strtolower($url . ' ' . $title);
    
    $categories = [
        // Vidéos & Divertissement
        'Vidéos' => ['youtube', 'netflix', 'twitch', 'disney', 'primevideo', 'dailymotion', 'vimeo', 'hulu', 'streaming', 'film', 'movie'],
        
        // Jeux
        'Jeux' => ['roblox', 'minecraft', 'epicgames', 'steam', 'ea.com', 'playstation', 'xbox', 'nintendo', 'games', 'jouer', 'miniclip', 'poki'],
        
        // Réseaux sociaux
        'Réseaux sociaux' => ['facebook', 'instagram', 'tiktok', 'twitter', 'x.com', 'snapchat', 'discord', 'reddit', 'pinterest', 'linkedin', 'social', 'chat', 'messenger'],
        
        // Éducation
        'Éducation' => ['wikipedia', 'khanacademy', 'duolingo', 'coursera', 'edx', 'ecole', 'learn', 'study', 'education', 'moodle', 'pronote', 'scolaire', 'universite'],
        
        // Adulte / Dangereux
        'Adulte' => ['pornhub', 'xvideos', 'chatroulette', 'omegle', 'sex', 'porn', 'nsfw', 'adult', 'xhamster', 'onlyfans'],
        
        // Violence / Armes
        'Violence' => ['gore', 'blood', 'weapon', 'gun', 'murder', 'kill', 'arme', 'violence', 'terror'],
        
        // Jeux d'argent (Gambling)
        'Jeux d\'argent' => ['casino', 'poker', 'bet', 'betclic', 'winamax', 'unibet', 'fdj', 'loto', 'gambling', 'roulette'],
        
        // Shopping
        'Shopping' => ['amazon', 'ebay', 'aliexpress', 'leboncoin', 'cdiscount', 'vinted', 'zalando', 'shein', 'shopping', 'store', 'shop', 'buy', 'acheter'],
        
        // Sports
        'Sports' => ['lequipe', 'eurosport', 'fifa', 'nba', 'nfl', 'sport', 'football', 'basketball', 'tennis', 'espn'],
        
        // Actualités
        'Actualités' => ['lemonde', 'lefigaro', 'bfmtv', 'francetv', 'cnn', 'bbc', 'news', 'actualite', 'journal', 'presse'],

        // Recherche / Utilitaires
        'Recherche' => ['google', 'bing', 'yahoo', 'duckduckgo', 'qwant', 'search', 'recherche']
    ];
    
    foreach ($categories as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text_to_search, $keyword) !== false) {
                return $category;
            }
        }
    }
    
    return 'Divers'; 
}
