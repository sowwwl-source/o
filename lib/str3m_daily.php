<?php
declare(strict_types=1);

require_once __DIR__ . '/str3m_media.php';

function str3m_daily_seed(?string $landSlug = null, ?string $date = null): string
{
	$scope = trim((string) $landSlug);
	$scope = $scope !== '' ? $scope : 'global';
	$day = $date !== null && trim($date) !== '' ? trim($date) : gmdate('Y-m-d');
	return $day . '|' . $scope . '|v1';
}

function str3m_seed_unit(string $seed, string $key): float
{
	$hash = hash('sha256', $seed . '|' . $key);
	$chunk = substr($hash, 0, 12);
	$number = hexdec($chunk);
	return $number / 0xFFFFFFFFFFFF;
}

function str3m_pick_daily_mood(string $seed): string
{
	$choices = [
		['name' => 'calm', 'weight' => 0.42],
		['name' => 'dense', 'weight' => 0.34],
		['name' => 'nocturnal', 'weight' => 0.24],
	];

	$cursor = str3m_seed_unit($seed, 'mood');
	$progress = 0.0;
	foreach ($choices as $choice) {
		$progress += $choice['weight'];
		if ($cursor <= $progress) {
			return $choice['name'];
		}
	}

	return 'calm';
}

function str3m_templates(): array
{
	return [
		[
			'name' => 'text_image_audio',
			'weight' => 0.38,
			'required' => ['text', 'image', 'audio'],
			'optional' => [],
			'moods' => ['dense', 'nocturnal'],
			'layout' => [
				'mode' => 'orbital',
				'motion' => 'slow',
				'visual_emphasis' => 'image',
				'overlay_text' => true,
			],
		],
		[
			'name' => 'text_image',
			'weight' => 0.42,
			'required' => ['text', 'image'],
			'optional' => [],
			'moods' => ['calm', 'dense', 'nocturnal'],
			'layout' => [
				'mode' => 'surface',
				'motion' => 'soft',
				'visual_emphasis' => 'text',
				'overlay_text' => false,
			],
		],
		[
			'name' => 'image_audio',
			'weight' => 0.2,
			'required' => ['image', 'audio'],
			'optional' => ['text'],
			'moods' => ['dense', 'nocturnal'],
			'layout' => [
				'mode' => 'drift',
				'motion' => 'ambient',
				'visual_emphasis' => 'image',
				'overlay_text' => true,
			],
		],
	];
}

function str3m_available_templates(array $itemsByType, string $mood): array
{
	$templates = [];
	foreach (str3m_templates() as $template) {
		if (!in_array($mood, $template['moods'], true)) {
			continue;
		}

		$isAvailable = true;
		foreach ($template['required'] as $requiredType) {
			if (empty($itemsByType[$requiredType])) {
				$isAvailable = false;
				break;
			}
		}

		if ($isAvailable) {
			$templates[] = $template;
		}
	}

	if ($templates !== []) {
		return $templates;
	}

	foreach (str3m_templates() as $template) {
		$isAvailable = true;
		foreach ($template['required'] as $requiredType) {
			if (empty($itemsByType[$requiredType])) {
				$isAvailable = false;
				break;
			}
		}

		if ($isAvailable) {
			$templates[] = $template;
		}
	}

	return $templates;
}

function str3m_pick_template(array $templates, string $seed): ?array
{
	if ($templates === []) {
		return null;
	}

	$bestTemplate = null;
	$bestScore = -1.0;
	foreach ($templates as $template) {
		$score = ((float) ($template['weight'] ?? 1)) * (0.35 + str3m_seed_unit($seed, 'template:' . ($template['name'] ?? 'unknown')));
		if ($score > $bestScore) {
			$bestScore = $score;
			$bestTemplate = $template;
		}
	}

	return $bestTemplate;
}

function str3m_pick_weighted_item(array $items, string $seed, string $slot, array $excludedIds = [], string $mood = ''): ?array
{
	$bestItem = null;
	$bestScore = -1.0;

	foreach ($items as $item) {
		$id = (string) ($item['id'] ?? '');
		if ($id === '' || in_array($id, $excludedIds, true)) {
			continue;
		}

		$moods = is_array($item['moods'] ?? null) ? $item['moods'] : [];
		$moodBoost = $mood !== '' && in_array($mood, $moods, true) ? 1.35 : ($moods === [] ? 1.05 : 0.88);
		$hintBoost = in_array($slot, is_array($item['layout_hints'] ?? null) ? $item['layout_hints'] : [], true) ? 1.18 : 1.0;
		$baseWeight = max(0.05, (float) ($item['weight'] ?? 1));
		$noise = 0.45 + str3m_seed_unit($seed, $slot . ':' . $id);
		$score = $baseWeight * $moodBoost * $hintBoost * $noise;

		if ($score > $bestScore) {
			$bestScore = $score;
			$bestItem = $item;
		}
	}

	return $bestItem;
}

function str3m_build_daily_stream(?string $landSlug = null, ?string $date = null): array
{
	$seed = str3m_daily_seed($landSlug, $date);
	$index = str3m_load_media_index();
	$publishable = str3m_filter_publishable_items($index['items'] ?? [], $landSlug);
	$itemsByType = str3m_group_items_by_type($publishable);
	$mood = str3m_pick_daily_mood($seed);
	$templates = str3m_available_templates($itemsByType, $mood);
	$template = str3m_pick_template($templates, $seed);

	if ($template === null) {
		return [
			'date' => substr($seed, 0, 10),
			'seed' => $seed,
			'scope' => trim((string) $landSlug) !== '' ? trim((string) $landSlug) : 'global',
			'mood' => $mood,
			'template' => 'empty',
			'layout' => [
				'mode' => 'quiet',
				'motion' => 'still',
				'visual_emphasis' => 'text',
				'overlay_text' => false,
			],
			'items' => [],
			'meta' => [
				'engine_version' => 1,
				'reason' => 'not_enough_media',
			],
		];
	}

	$selected = [];
	$selectedIds = [];
	foreach ($template['required'] as $slotType) {
		$item = str3m_pick_weighted_item($itemsByType[$slotType] ?? [], $seed, 'required:' . $slotType, $selectedIds, $mood);
		if ($item === null) {
			continue;
		}

		$selected[$slotType] = $item;
		$selectedIds[] = (string) $item['id'];
	}

	foreach ($template['optional'] as $slotType) {
		$item = str3m_pick_weighted_item($itemsByType[$slotType] ?? [], $seed, 'optional:' . $slotType, $selectedIds, $mood);
		if ($item === null) {
			continue;
		}

		$selected[$slotType] = $item;
		$selectedIds[] = (string) $item['id'];
	}

	return [
		'date' => substr($seed, 0, 10),
		'seed' => $seed,
		'scope' => trim((string) $landSlug) !== '' ? trim((string) $landSlug) : 'global',
		'mood' => $mood,
		'template' => (string) ($template['name'] ?? 'text_image'),
		'layout' => is_array($template['layout'] ?? null) ? $template['layout'] : [],
		'items' => $selected,
		'meta' => [
			'engine_version' => 1,
			'available_items' => count($publishable),
		],
	];
}