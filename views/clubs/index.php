<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="page-header">
    <h2>Clubs List</h2>
    <a href="?action=create_club" class="btn btn-primary">Add New Club</a>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Code</th>
            <th>Name</th>
            <th>Department</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($clubs as $club): ?>
            <tr>
                <td><?php echo htmlspecialchars($club['club_id']); ?></td>
                <td><?php echo htmlspecialchars($club['club_code']); ?></td>
                <td><?php echo htmlspecialchars($club['club_name']); ?></td>
                <td><?php echo htmlspecialchars($club['department_name'] ?? 'N/A'); ?></td>
                <td>
                    <span class="badge badge-<?php echo $club['status'] == 'active' ? 'success' : 'danger'; ?>">
                        <?php echo htmlspecialchars(ucfirst($club['status'])); ?>
                    </span>
                </td>
                <td>
                    <a href="?action=show_club&id=<?php echo $club['club_id']; ?>" class="btn btn-sm btn-info">View</a>
                    <a href="?action=edit_club&id=<?php echo $club['club_id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                    <form action="?action=deactivate_club" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to deactivate this club?');">
                        <input type="hidden" name="id" value="<?php echo $club['club_id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Deactivate</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($clubs)): ?>
            <tr>
                <td colspan="6" class="text-center">No clubs found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
