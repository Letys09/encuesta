create table enc_pregunta(
	ID_pregunta int primary key AUTO_INCREMENT,
	pregunta varchar(255) not null,
	tipo tinyint not null default 1 comment '1 -> abierta, 2 -> opción múltiple',
	opciones text,
	fecha datetime not null,
	status tinyint not null default 1
);

create table enc_encuesta(
	ID_encuesta int primary key AUTO_INCREMENT,
	nombre varchar(255) not null,
	fecha datetime not null,
	status tinyint not null default 1
);

create table enc_universo(
	ID_universo int primary key AUTO_INCREMENT,
	ID_encuesta int not null,
	ID_pregunta int not null,
	constraint enc_universo_fk_encuesta foreign key(ID_encuesta) references enc_encuesta(ID_encuesta),
	constraint enc_universo_fk_pregunta foreign key(ID_pregunta) references enc_pregunta(ID_pregunta)
);

create table enc_url(
	ID_url int primary key AUTO_INCREMENT,
	ID_encuesta int not null,
	num_preguntas int not null,
	fecha datetime not null,
	urltxt varchar(300) not null,
	status tinyint not null default 1,
	constraint enc_uri_fk_encuesta foreign key(ID_encuesta) references enc_encuesta(ID_encuesta)
);

create table enc_intento(
	ID_intento int primary key AUTO_INCREMENT,
	ID_url int not null,
	invitado_id varchar(50) not null,
	nombre varchar(255) not null,
	correo varchar(255) not null,
	inicio datetime not null,
	final datetime not null,
	comentarios varchar(1000) null,
	constraint enc_intento_fk_url foreign key(ID_url) references enc_url(ID_url)
);

create table enc_respuesta(
	ID_respuesta int primary key AUTO_INCREMENT,
	ID_intento int not null,
	ID_pregunta int not null,
	respuesta varchar(1000) not null,
	constraint enc_respuesta_fk_intento foreign key(ID_intento) references enc_intento(ID_intento),
	constraint enc_respuesta_fk_pregunta foreign key(ID_pregunta) references enc_pregunta(ID_pregunta)
);

CREATE TABLE `usuario` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre` varchar(50) DEFAULT NULL,
  `apellidos` varchar(50) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `celular` varchar(10) DEFAULT NULL,
  `password` varchar(40) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `usuario` (`id`, `nombre`, `apellidos`, `email`, `celular`, `password`, `status`) VALUES (NULL, 'John', 'Doe', 'leticia@ddsmedia.net', '0101010101', '19e443c9d171d2f0d3ad1157fed5a39d', '1');

ALTER TABLE `enc_pregunta` ADD `escala` VARCHAR(5) NULL AFTER `opciones`, ADD `icono` TINYINT(1) NULL COMMENT '0 -> &#9733; Estrellas\r\n1 -> &#128512; Caritas\r\n2 -> &#9635; Números' AFTER `escala`;

create table enc_valor_qr(
	id int primary key AUTO_INCREMENT,
	codigo char(2) not null,
	valor varchar(255) not null,
	fecha datetime not null default current_timestamp on update current_timestamp,
	constraint uq_enc_valor_qr_codigo unique(codigo)
);

CREATE TABLE enc_valor_resp(
	id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    enc_valor_qr_id INT NOT NULL,
    invitado_id VARCHAR(50) NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE enc_finalista (
	id INT PRIMARY KEY AUTO_INCREMENT,
	ID_encuesta INT NOT NULL,
	ID_pregunta INT NOT NULL,
	invitado_id VARCHAR(50) NOT NULL,
	fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT uq_enc_finalista UNIQUE (ID_encuesta, ID_pregunta, invitado_id),
	CONSTRAINT fk_finalista_encuesta FOREIGN KEY (ID_encuesta) REFERENCES enc_encuesta(ID_encuesta),
	CONSTRAINT fk_finalista_pregunta FOREIGN KEY (ID_pregunta) REFERENCES enc_pregunta(ID_pregunta)
);

CREATE TABLE enc_votacion_postulacion (
	id INT PRIMARY KEY AUTO_INCREMENT,
	ID_encuesta INT NOT NULL,
	ID_pregunta_postulacion INT NOT NULL,
	ID_pregunta_votacion INT NOT NULL,
	fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT uq_votacion_postulacion UNIQUE (ID_encuesta, ID_pregunta_postulacion),
	CONSTRAINT fk_vp_encuesta FOREIGN KEY (ID_encuesta) REFERENCES enc_encuesta(ID_encuesta),
	CONSTRAINT fk_vp_postulacion FOREIGN KEY (ID_pregunta_postulacion) REFERENCES enc_pregunta(ID_pregunta),
	CONSTRAINT fk_vp_votacion FOREIGN KEY (ID_pregunta_votacion) REFERENCES enc_pregunta(ID_pregunta)
);