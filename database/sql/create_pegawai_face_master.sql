-- Table untuk menyimpan master foto wajah pegawai
-- Charset: latin1 sesuai permintaan

CREATE TABLE `rsia_pegawai_face_master` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pegawai_id` int(11) NOT NULL,
  `nik` varchar(30) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `face_encoding` text NULL COMMENT 'Optional: face embedding untuk ML matching',
  `registered_at` datetime NOT NULL,
  `updated_at` datetime NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pegawai` (`pegawai_id`),
  KEY `idx_nik` (`nik`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fk_rsia_pegawai_face_master_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Master foto wajah pegawai untuk E-Presensi';
