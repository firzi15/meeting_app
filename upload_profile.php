<?php
// Fitur upload foto profil telah dinonaktifkan.
http_response_code(403);
echo json_encode(['success' => false, 'message' => 'Fitur ini tidak tersedia.']);
exit;
