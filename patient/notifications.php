<?php
require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only patients can access
requireRole('Patient');

// Get all notifications with Doctor's Name and Sender_ID
$stmt = $conn->prepare("SELECT N.Notification_ID, N.Alert_Type, N.Message, N.Sent_At, N.Status, N.Sender_ID, U.Full_Name AS Doctor_Name
                        FROM Notifications N
                        LEFT JOIN User U ON N.Sender_ID = U.User_ID
                        WHERE N.User_ID = ?
                        ORDER BY N.Sent_At DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Display any alerts (e.g., reply sent confirmation)
displayAlert();
?>

<div class="patient-notifications">
    <h2>Notifications</h2>

    <div class="back-link">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <?php if (!empty($notifications)): ?>
        <ul class="notification-list">
            <?php foreach ($notifications as $notification): ?>
                <li class="<?= ($notification['Status'] === 'Sent') ? 'unread' : 'read' ?>">
                    <strong><?= htmlspecialchars($notification['Alert_Type']) ?>:</strong>
                    <?= htmlspecialchars($notification['Message']) ?>

                    <?php if (!empty($notification['Doctor_Name'])): ?>
                        <br><small><i>From Dr. <?= htmlspecialchars($notification['Doctor_Name']) ?></i></small>
                    <?php endif; ?>

                    <span class="timestamp"><?= date('M j, g:i A', strtotime($notification['Sent_At'])) ?></span>

                    <div class="notification-actions">
                        <?php if ($notification['Status'] === 'Sent'): ?>
                            <form action="mark_notification_read.php" method="post" style="display:inline;">
                                <input type="hidden" name="notification_id" value="<?= $notification['Notification_ID'] ?>">
                                <button type="submit" class="btn btn-sm">Mark as Read</button>
                            </form>
                        <?php endif; ?>

                        <!-- Reply Form -->
                        <?php if ($notification['Sender_ID']): // Only show reply if there's a sender ?>
                            <form action="send_reply.php" method="post" class="reply-form" style="display:inline;">
                                <input type="hidden" name="notification_id" value="<?= $notification['Notification_ID'] ?>">
                                <input type="hidden" name="receiver_id" value="<?= $notification['Sender_ID'] ?>">
                                <input type="text" name="reply_message" placeholder="Type your reply..." required class="message-input">
                                <button type="submit" class="btn btn-sm" title="Send Reply">
                                    <i class="fas fa-reply"></i> Reply
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No notifications available.</p>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>