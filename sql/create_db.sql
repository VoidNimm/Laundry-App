-- create_db.sql
DROP DATABASE IF EXISTS laundry_fixed;
CREATE DATABASE laundry_fixed CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laundry_fixed;

CREATE TABLE tb_outlet (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  alamat TEXT,
  tlp VARCHAR(15),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tb_user (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(30) NOT NULL UNIQUE,
  password TEXT NOT NULL,
  id_outlet INT(11),
  role ENUM('admin','kasir','owner') NOT NULL DEFAULT 'kasir',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_outlet) REFERENCES tb_outlet(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tb_paket (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_outlet INT(11) NOT NULL,
  jenis ENUM('kiloan','selimut','bed_cover','kaos','lain') NOT NULL,
  nama_paket VARCHAR(100) NOT NULL,
  harga INT(11) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_outlet) REFERENCES tb_outlet(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tb_member (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  alamat TEXT,
  jenis_kelamin ENUM('L','P'),
  tlp VARCHAR(15),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tb_transaksi (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_outlet INT(11) NOT NULL,
  kode_invoice VARCHAR(100) NOT NULL UNIQUE,
  id_member INT(11) DEFAULT NULL,
  tgl DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  batas_waktu DATETIME DEFAULT NULL,
  tgl_bayar DATETIME DEFAULT NULL,
  biaya_tambahan INT(11) DEFAULT 0,
  diskon DOUBLE DEFAULT 0,
  pajak INT(11) DEFAULT 0,
  status ENUM('baru','proses','selesai','diambil') DEFAULT 'baru',
  dibayar ENUM('dibayar','belum_dibayar') DEFAULT 'belum_dibayar',
  id_user INT(11) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_outlet) REFERENCES tb_outlet(id) ON DELETE CASCADE,
  FOREIGN KEY (id_member) REFERENCES tb_member(id) ON DELETE SET NULL,
  FOREIGN KEY (id_user) REFERENCES tb_user(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tb_detail_transaksi (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_transaksi INT(11) NOT NULL,
  id_paket INT(11) NOT NULL,
  qty DOUBLE DEFAULT 1,
  keterangan TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_transaksi) REFERENCES tb_transaksi(id) ON DELETE CASCADE,
  FOREIGN KEY (id_paket) REFERENCES tb_paket(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE INDEX idx_transaksi_outlet ON tb_transaksi(id_outlet);
CREATE INDEX idx_transaksi_kode ON tb_transaksi(kode_invoice);

-- sample data
INSERT INTO tb_outlet (nama, alamat, tlp) VALUES ('Outlet Pusat', 'Jl. Contoh No.1', '08123456789');

INSERT INTO tb_paket (id_outlet, jenis, nama_paket, harga) VALUES
(1, 'kiloan', 'Cuci Kiloan', 8000),
(1, 'kiloan', 'Cuci & Setrika', 12000),
(1, 'bed_cover', 'Cuci Bed Cover', 50000);

INSERT INTO tb_member (nama, alamat, jenis_kelamin, tlp) VALUES
('Budi', 'Jl. Mawar 1', 'L', '081300000');
