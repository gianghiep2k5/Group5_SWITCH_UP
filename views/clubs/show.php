<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="page-header">
    <h2>Club Details: <?php echo htmlspecialchars($club['club_name']); ?></h2>
    <div>
        <a href="?action=edit_club&id=<?php echo $club['club_id']; ?>" class="btn btn-warning">Edit Club</a>
        <a href="?action=list_clubs" class="btn btn-secondary">Back to List</a>
    </div>
</div>

<div class="card mb-4">
    <p><strong>Code:</strong> <?php echo htmlspecialchars($club['club_code']); ?></p>
    <p><strong>Department:</strong> <?php echo htmlspecialchars($club['department_name'] ?? 'N/A'); ?></p>
    <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($club['description'] ?? '')); ?></p>
    <p><strong>Founded Date:</strong> <?php echo htmlspecialchars($club['founded_date']); ?></p>
    <p><strong>Status:</strong> <?php echo htmlspecialchars($club['status']); ?></p>
</div>

<div class="page-header">
    <h3>Club Members</h3>
</div>

<div class="card mb-4">
    <form action="?action=add_member" method="POST" class="form-inline">
        <input type="hidden" name="club_id" value="<?php echo $club['club_id']; ?>">
        
        <div class="form-group" style="display:inline-block; margin-right:10px;">
            <select name="user_id" required>
                <option value="">-- Select User --</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="display:inline-block; margin-right:10px;">
            <select name="position" required>
                <option value="member">Member</option>
                <option value="team_leader">Team Leader</option>
                <option value="vice_president">Vice President</option>
                <option value="president">President</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">Add Member</button>
    </form>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Member Code</th>
            <th>Name</th>
            <th>Email</th>
            <th>Position</th>
            <th>Joined Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($members as $member): ?>
            <tr>
                <td><?php echo htmlspecialchars($member['member_code']); ?></td>
                <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                <td><?php echo htmlspecialchars($member['email']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $member['position']))); ?></td>
                <td><?php echo htmlspecialchars($member['joined_date']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($member['status'])); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($members)): ?>
            <tr>
                <td colspan="6" class="text-center">No members yet.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
