<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="page-header">
    <h2>Edit Club</h2>
    <a href="?action=list_clubs" class="btn btn-secondary">Back to List</a>
</div>

<div class="card">
    <form action="?action=edit_club&id=<?php echo $club['club_id']; ?>" method="POST" id="clubForm">
        <div class="form-group">
            <label for="department_id">Department</label>
            <select name="department_id" id="department_id" required>
                <option value="">-- Select Department --</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['department_id']; ?>" <?php echo ($dept['department_id'] == $club['department_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="club_name">Club Name</label>
            <input type="text" name="club_name" id="club_name" value="<?php echo htmlspecialchars($club['club_name'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="club_code">Club Code</label>
            <input type="text" name="club_code" id="club_code" value="<?php echo htmlspecialchars($club['club_code'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" rows="4"><?php echo htmlspecialchars($club['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label for="founded_date">Founded Date</label>
            <input type="date" name="founded_date" id="founded_date" value="<?php echo htmlspecialchars($club['founded_date'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="active" <?php echo ($club['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo ($club['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Update Club</button>
    </form>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
