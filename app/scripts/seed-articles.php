<?php
/**
 * Seeds the site with random demo articles.
 *
 *   ./bin/seed              20 articles, about 30% premium
 *   ./bin/seed 50 60        50 articles, about 60% premium
 *   ./bin/seed clean        delete every article created by this script
 *
 * Runs through WP-CLI (wp eval-file), see bin/seed.
 */

if (!defined('WP_CLI') || !WP_CLI) {
    exit("Run this through WP-CLI: ./bin/seed\n");
}

$seed_meta = '_havenpost_seeded';  // marks articles made by this script
$premium_meta = '_havenpost_premium'; // same key as Havenpost\Article\PremiumGate::META_KEY

// ---------------------------------------------------------------- clean up
if (($args[0] ?? '') === 'clean') {
    $ids = get_posts([
        'post_type'   => 'post',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_key'    => $seed_meta,
        'meta_value'  => '1',
    ]);

    foreach ($ids as $id) {
        wp_delete_post($id, true);
    }

    WP_CLI::success(sprintf('Deleted %d seeded articles.', count($ids)));
    return;
}

// ---------------------------------------------------------------- arguments
$count = isset($args[0]) && ctype_digit((string) $args[0]) ? max(1, (int) $args[0]) : 20;
$premium_pct = isset($args[1]) && ctype_digit((string) $args[1]) ? min(100, (int) $args[1]) : 30;

// ---------------------------------------------------------------- categories
$category_ids = [];
foreach (['World', 'Politics', 'Economy', 'Technology', 'Sports', 'Culture'] as $name) {
    $term = term_exists($name, 'category');
    if (!$term) {
        $term = wp_insert_term($name, 'category');
    }
    if (!is_wp_error($term)) {
        $category_ids[] = (int) (is_array($term) ? $term['term_id'] : $term);
    }
}

// ---------------------------------------------------------------- authors
$authors = [];
$demo_authors = [
    'anna.devries' => 'Anna de Vries',
    'jan.bakker'   => 'Jan Bakker',
    'sara.jansen'  => 'Sara Jansen',
];
foreach ($demo_authors as $login => $display_name) {
    $user_id = username_exists($login);
    if (!$user_id) {
        $user_id = wp_insert_user([
            'user_login'   => $login,
            'user_pass'    => wp_generate_password(24),
            'user_email'   => $login . '@example.com',
            'display_name' => $display_name,
            'role'         => 'author',
        ]);
    }
    if (!is_wp_error($user_id)) {
        $authors[] = (int) $user_id;
    }
}
if (!$authors) {
    $authors = [1];
}

// ---------------------------------------------------------------- text banks
$subjects = [
    'The city council', 'The central bank', 'Local police', 'Researchers', 'The government',
    'A major football club', 'The transport authority', 'A leading tech company',
    'The national museum', 'A group of farmers',
];
$verbs = ['approves', 'rejects', 'unveils', 'investigates', 'delays', 'defends', 'announces', 'questions'];
$objects = [
    'a new housing plan', 'sweeping budget cuts', 'stricter climate rules', 'the stadium project',
    'new data regulations', 'a surprise merger', 'the winter timetable', 'plans for a new bridge',
    'an overhaul of public transport',
];
$tails = [
    'after months of debate', 'amid public criticism', 'in a surprise move', 'ahead of the election',
    'despite warnings from experts', 'as costs rise', 'following a lengthy review',
];
$sentences = [
    'The decision follows several weeks of discussion between officials and local residents.',
    'Critics argue that the plan does not go far enough, while supporters call it a necessary first step.',
    'According to people familiar with the matter, a final vote is expected within the next month.',
    'Experts say the effects will only become clear over the coming years.',
    'A spokesperson declined to comment on the details but confirmed that talks are ongoing.',
    'The announcement was met with mixed reactions on social media and in the streets.',
    'Similar measures have been tried elsewhere with varying results.',
    'Costs are estimated at several million euros, though the exact figure is still under review.',
    'Opposition parties have called for an independent investigation.',
    'Residents will be informed through public meetings later this month.',
    'The change is scheduled to take effect at the start of next year.',
    'Analysts expect the market to react cautiously in the short term.',
    'Several organisations have already announced that they will challenge the decision.',
    'For many families, the outcome could mean a noticeable difference in daily life.',
];

$pick = static fn (array $items): string => $items[array_rand($items)];
$some_sentences = static function (int $min, int $max) use ($sentences): string {
    $pool = $sentences;
    shuffle($pool);

    return implode(' ', array_slice($pool, 0, random_int($min, $max)));
};

// ---------------------------------------------------------------- create
$created = 0;
$premium_count = 0;

for ($i = 0; $i < $count; $i++) {
    $title = sprintf('%s %s %s %s', $pick($subjects), $pick($verbs), $pick($objects), $pick($tails));
    $intro = $some_sentences(1, 2);

    $paragraphs = [];
    for ($p = random_int(4, 7); $p > 0; $p--) {
        $paragraphs[] = "<!-- wp:paragraph -->\n<p>" . $some_sentences(3, 5) . "</p>\n<!-- /wp:paragraph -->";
    }
    $body = implode("\n\n", $paragraphs);

    $pool = $category_ids;
    shuffle($pool);
    $categories = array_slice($pool, 0, $pool ? random_int(1, min(2, count($pool))) : 0);

    $date = wp_date('Y-m-d H:i:s', time() - random_int(0, 14 * DAY_IN_SECONDS));
    $is_premium = random_int(1, 100) <= $premium_pct;

    $meta = [$seed_meta => '1'];
    if ($is_premium) {
        $meta[$premium_meta] = '1';
    }

    $post_id = wp_insert_post([
        'post_type'     => 'post',
        'post_status'   => 'publish',
        'post_title'    => $title,
        'post_excerpt'  => $intro,
        'post_content'  => $body,
        'post_author'   => $authors[array_rand($authors)],
        'post_category' => $categories,
        'post_date'     => $date,
        'post_date_gmt' => get_gmt_from_date($date),
        'meta_input'    => $meta,
    ], true);

    if (is_wp_error($post_id)) {
        WP_CLI::warning('Could not create an article: ' . $post_id->get_error_message());
        continue;
    }

    $created++;
    $premium_count += $is_premium ? 1 : 0;
    WP_CLI::log(sprintf('#%d %s%s', $post_id, $title, $is_premium ? '  [premium]' : ''));
}

WP_CLI::success(sprintf(
    'Created %d articles (%d premium). Remove them again with: ./bin/seed clean',
    $created,
    $premium_count
));
