<?php
require_once __DIR__ . '/../src/bootstrap.php';

$pageTitle  = 'Properties';
$activePage = 'Properties';

// Handle delete
if (isset($_GET['delete'])) {
    Database::deleteProperty((int)$_GET['delete']);
    header('Location: /properties.php?msg=deleted');
    exit;
}

// Handle form save
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name']      ?? '');
    $location  = trim($_POST['location']  ?? '');
    $base      = (float)($_POST['base_price']  ?? 0);
    $min       = (float)($_POST['min_price']   ?? 0);
    $max       = (float)($_POST['max_price']   ?? 0);
    $roomType  = $_POST['room_type'] ?? 'entire_unit';
    $bedrooms  = (int)($_POST['bedrooms']  ?? 1);
    $propId    = (int)($_POST['id']        ?? 0);

    $allowed_locations = MarketData::getAvailableLocations();
    $allowed_rooms     = ['entire_unit', 'private_room', 'shared_room'];

    if (!$name)                                    $errors[] = 'Property name is required.';
    if (!in_array($location, $allowed_locations))  $errors[] = 'Invalid location.';
    if (!in_array($roomType, $allowed_rooms))      $errors[] = 'Invalid room type.';
    if ($min >= $max)                              $errors[] = 'Min price must be less than max price.';
    if ($base < $min || $base > $max)             $errors[] = 'Base price must be between min and max.';
    if ($bedrooms < 1 || $bedrooms > 10)          $errors[] = 'Bedrooms must be 1–10.';

    if (empty($errors)) {
        Database::upsertProperty([
            'id'         => $propId ?: null,
            'user_id'    => Database::getDemoUserId(),
            'name'       => $name,
            'location'   => $location,
            'base_price' => $base,
            'min_price'  => $min,
            'max_price'  => $max,
            'room_type'  => $roomType,
            'bedrooms'   => $bedrooms,
        ]);
        header('Location: /properties.php?msg=saved');
        exit;
    }
}

$properties = Database::getAllProperties();
$locations  = MarketData::getAvailableLocations();

// Editing?
$editing = null;
if (isset($_GET['edit'])) {
    $editing = Database::getProperty((int)$_GET['edit']);
}

$msg = $_GET['msg'] ?? '';
require_once __DIR__ . '/partials/header.php';
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Properties</h2>
        <p class="text-sm text-gray-500 mt-1">Manage your short-term rental listings</p>
    </div>
    <a href="/properties.php?add=1"
       class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
        + Add Property
    </a>
</div>

<?php if ($msg === 'saved'):  ?><div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">✅ Property saved.</div><?php endif; ?>
<?php if ($msg === 'deleted'):?><div class="mb-4 p-3 bg-red-50   border border-red-200   rounded-lg text-red-800   text-sm">🗑️  Property deleted.</div><?php endif; ?>
<?php if (!empty($errors)):   ?>
<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
    <?php foreach ($errors as $e): ?><p>• <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Property list -->
<?php if (!empty($properties)): ?>
<div class="space-y-3 mb-8">
<?php foreach ($properties as $prop): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center justify-between">
    <div>
        <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($prop['name']) ?></h4>
        <p class="text-sm text-gray-500 mt-0.5">
            📍 <?= htmlspecialchars($prop['location']) ?>
            · <?= str_replace('_', ' ', ucwords($prop['room_type'], '_')) ?>
            · <?= $prop['bedrooms'] ?> BR
        </p>
    </div>
    <div class="flex items-center gap-6">
        <div class="text-right text-sm">
            <p class="font-semibold text-gray-900">Base: RM <?= number_format($prop['base_price'], 0) ?></p>
            <p class="text-gray-400 text-xs">RM <?= number_format($prop['min_price'], 0) ?> – RM <?= number_format($prop['max_price'], 0) ?></p>
        </div>
        <div class="flex gap-2">
            <a href="/properties.php?edit=<?= $prop['id'] ?>"
               class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">✏️ Edit</a>
            <button onclick="confirmDelete(<?= $prop['id'] ?>, '<?= addslashes($prop['name']) ?>')"
                    class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50">🗑️</button>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add / Edit form -->
<?php if (isset($_GET['add']) || $editing || !empty($errors)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-6">
        <?= $editing ? '✏️ Edit: ' . htmlspecialchars($editing['name']) : '➕ Add New Property' ?>
    </h3>

    <form method="post" class="grid grid-cols-2 gap-6">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>

        <!-- Left column -->
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Property Name</label>
                <input type="text" name="name" required
                       value="<?= htmlspecialchars($editing['name'] ?? ($_POST['name'] ?? '')) ?>"
                       placeholder="e.g. KLCC Sky Suite"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                <select name="location" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?= htmlspecialchars($loc) ?>"
                        <?= ($editing['location'] ?? ($_POST['location'] ?? '')) === $loc ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Room Type</label>
                <select name="room_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                    <?php foreach (['entire_unit' => 'Entire Unit', 'private_room' => 'Private Room', 'shared_room' => 'Shared Room'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($editing['room_type'] ?? 'entire_unit') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bedrooms</label>
                <input type="number" name="bedrooms" min="1" max="10"
                       value="<?= (int)($editing['bedrooms'] ?? ($_POST['bedrooms'] ?? 1)) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <!-- Right column -->
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Base Price (RM)</label>
                <input type="number" name="base_price" min="50" max="2000" step="10" required
                       value="<?= (float)($editing['base_price'] ?? ($_POST['base_price'] ?? 200)) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Min Price (RM) <span class="text-gray-400 font-normal">— hard floor</span></label>
                <input type="number" name="min_price" min="30" max="2000" step="10" required
                       value="<?= (float)($editing['min_price'] ?? ($_POST['min_price'] ?? 150)) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Price (RM) <span class="text-gray-400 font-normal">— hard ceiling</span></label>
                <input type="number" name="max_price" min="50" max="5000" step="10" required
                       value="<?= (float)($editing['max_price'] ?? ($_POST['max_price'] ?? 350)) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="bg-blue-50 rounded-lg p-3 text-xs text-blue-700">
                💡 The pricing engine will <strong>never</strong> exceed min/max regardless of market conditions.
            </div>
        </div>

        <div class="col-span-2 flex gap-3 pt-2">
            <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                💾 <?= $editing ? 'Update Property' : 'Add Property' ?>
            </button>
            <a href="/properties.php" class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                Cancel
            </a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
