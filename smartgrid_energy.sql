-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3307
-- Généré le : mer. 01 juil. 2026 à 22:58
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `smartgrid_energy`
--

-- --------------------------------------------------------

--
-- Structure de la table `alertes`
--

CREATE TABLE `alertes` (
  `id` int(11) NOT NULL,
  `id_compteur` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `niveau` enum('info','warning','critique') DEFAULT NULL,
  `date_alerte` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `alertes`
--

INSERT INTO `alertes` (`id`, `id_compteur`, `message`, `niveau`, `date_alerte`) VALUES
(1, 5, 'Sous-tension detectee', 'warning', '2026-06-26 20:57:40'),
(2, 5, 'Sous-tension detectee', 'warning', '2026-06-26 20:57:54'),
(3, 5, 'Sous-tension detectee', 'warning', '2026-06-26 20:58:10'),
(4, 5, 'Sous-tension detectee', 'warning', '2026-06-26 20:58:24'),
(5, 5, 'Sous-tension detectee', 'warning', '2026-06-26 20:58:39'),
(6, 5, 'Sous-tension detectee', 'warning', '2026-06-26 20:58:54'),
(7, 5, 'Sous-tension detectee', 'warning', '2026-06-26 20:59:09'),
(8, 5, 'Sous-tension detectee', 'warning', '2026-06-26 20:59:24');

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `clients`
--

INSERT INTO `clients` (`id`, `nom`, `telephone`, `adresse`, `date_creation`) VALUES
(1, 'abigael', '0978481787', 'lshi', '0000-00-00 00:00:00'),
(2, 'ket', '0999999999', 'lubumbashi', '2026-04-16 14:23:30'),
(3, 'bob', '0987654321', 'craa', '2026-04-16 15:43:27'),
(4, 'alice', '0971324443', '09,av kasapa, Q.kasapa,c/annexe', '2026-07-01 10:51:50');

-- --------------------------------------------------------

--
-- Structure de la table `compteurs`
--

CREATE TABLE `compteurs` (
  `id` int(11) NOT NULL,
  `numero_compteur` varchar(50) DEFAULT NULL,
  `id_client` int(11) DEFAULT NULL,
  `statut` enum('actif','inactif') DEFAULT 'actif',
  `date_installation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `compteurs`
--

INSERT INTO `compteurs` (`id`, `numero_compteur`, `id_client`, `statut`, `date_installation`) VALUES
(1, '2', 1, '', '0000-00-00 00:00:00'),
(5, 'SG001', 1, 'actif', '2026-04-16 14:30:32'),
(6, '122', 3, 'actif', '2026-04-16 15:44:40'),
(7, 'SG002', 4, 'actif', '2026-07-01 10:52:18');

-- --------------------------------------------------------

--
-- Structure de la table `consommations`
--

CREATE TABLE `consommations` (
  `id` int(11) NOT NULL,
  `id_compteur` int(11) DEFAULT NULL,
  `tension` float DEFAULT NULL,
  `courant` float DEFAULT NULL,
  `puissance` float DEFAULT NULL,
  `energie` float DEFAULT NULL,
  `date_mesure` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `consommations`
--

INSERT INTO `consommations` (`id`, `id_compteur`, `tension`, `courant`, `puissance`, `energie`, `date_mesure`) VALUES
(2, 1, 215, 2, 430, 2, '2026-04-15 14:12:01'),
(3, 1, 223, 8, 1784, 4, '2026-04-16 14:19:23'),
(5, 1, 220, 5, 1100, 2.5, '2026-04-16 14:37:38'),
(6, 1, 220, 5, 110, 2.4, '2026-04-16 14:42:25'),
(7, 1, 234, 6, 1404, 2, '2026-04-16 14:59:23'),
(8, 1, 233, 9, 2097, 4, '2026-04-16 15:00:36'),
(9, 1, 232, 10, 2320, 5, '2026-04-16 15:01:04'),
(10, 1, 235, 7, 1645, 5, '2026-04-16 15:42:31'),
(11, 1, 236, 10, 2360, 5, '2026-04-16 15:42:42'),
(12, 1, 228, 2, 456, 4, '2026-04-16 15:42:44'),
(13, 1, 218, 7, 1526, 3, '2026-04-16 15:42:45'),
(14, 1, 211, 1, 211, 5, '2026-04-16 15:42:47'),
(15, 1, 214, 8, 1712, 5, '2026-04-16 15:42:48'),
(16, 1, 237, 9, 2133, 1, '2026-04-30 19:17:08'),
(17, 5, 220, 1.2, 264, 0.5, '2026-06-20 21:12:07'),
(18, 5, 220, 1.2, 264, 0.5, '2026-06-26 20:16:17'),
(19, 5, 221, 1.1, 243, 0.6, '2026-06-26 20:16:43'),
(20, 5, 165.8, 0.034, 3.7, 0.026, '2026-06-26 20:57:40'),
(21, 5, 168.1, 0.034, 3.7, 0.026, '2026-06-26 20:57:54'),
(22, 5, 167.5, 0.034, 3.7, 0.026, '2026-06-26 20:58:10'),
(23, 5, 168.4, 0.034, 3.7, 0.026, '2026-06-26 20:58:24'),
(24, 5, 170.8, 0.033, 3.7, 0.026, '2026-06-26 20:58:39'),
(25, 5, 174.3, 0.033, 3.7, 0.026, '2026-06-26 20:58:54'),
(26, 5, 171.4, 0.186, 31.6, 0.026, '2026-06-26 20:59:09'),
(27, 5, 172.6, 0.185, 31.7, 0.026, '2026-06-26 20:59:24'),
(28, 1, 228, 2, 456, 1, '2026-07-01 10:50:12'),
(29, 1, 229, 5, 1145, 2, '2026-07-01 10:50:17');

-- --------------------------------------------------------

--
-- Structure de la table `factures`
--

CREATE TABLE `factures` (
  `id` int(11) NOT NULL,
  `id_client` int(11) DEFAULT NULL,
  `energie_totale` float DEFAULT NULL,
  `montant` float DEFAULT NULL,
  `statut` enum('payee','non_payee') DEFAULT 'non_payee',
  `date_facture` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `factures`
--

INSERT INTO `factures` (`id`, `id_client`, `energie_totale`, `montant`, `statut`, `date_facture`) VALUES
(2, 1, 0, 0, 'non_payee', '2026-04-16 14:45:27'),
(3, 1, 12.5, 1.875, 'non_payee', '2026-04-16 14:47:48'),
(4, 1, NULL, 3500, 'payee', '2026-04-16 14:50:10'),
(5, 1, NULL, 10, 'non_payee', '2026-04-16 14:50:27'),
(6, 3, NULL, 10, 'payee', '2026-04-16 15:45:03'),
(7, 1, 5, 0.75, 'payee', '2026-06-26 21:07:07'),
(8, 1, 5, 1, 'payee', '2026-06-26 21:07:30'),
(9, 2, 0, 0, 'non_payee', '2026-06-29 08:35:24');

-- --------------------------------------------------------

--
-- Structure de la table `predictions`
--

CREATE TABLE `predictions` (
  `id` int(11) NOT NULL,
  `id_compteur` int(11) DEFAULT NULL,
  `prediction` float DEFAULT NULL,
  `date_prediction` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `predictions`
--

INSERT INTO `predictions` (`id`, `id_compteur`, `prediction`, `date_prediction`) VALUES
(2, 1, 0, '2026-04-16'),
(3, 1, 14.7, '2026-04-16'),
(4, 1, 3.1, '2026-04-16');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL,
  `role` enum('admin','client') DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `alertes`
--
ALTER TABLE `alertes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_compteur` (`id_compteur`);

--
-- Index pour la table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `compteurs`
--
ALTER TABLE `compteurs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_client` (`id_client`);

--
-- Index pour la table `consommations`
--
ALTER TABLE `consommations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_compteur` (`id_compteur`);

--
-- Index pour la table `factures`
--
ALTER TABLE `factures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_client` (`id_client`);

--
-- Index pour la table `predictions`
--
ALTER TABLE `predictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_compteur` (`id_compteur`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `alertes`
--
ALTER TABLE `alertes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `compteurs`
--
ALTER TABLE `compteurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `consommations`
--
ALTER TABLE `consommations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT pour la table `factures`
--
ALTER TABLE `factures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `predictions`
--
ALTER TABLE `predictions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `alertes`
--
ALTER TABLE `alertes`
  ADD CONSTRAINT `alertes_ibfk_1` FOREIGN KEY (`id_compteur`) REFERENCES `compteurs` (`id`);

--
-- Contraintes pour la table `compteurs`
--
ALTER TABLE `compteurs`
  ADD CONSTRAINT `compteurs_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id`);

--
-- Contraintes pour la table `consommations`
--
ALTER TABLE `consommations`
  ADD CONSTRAINT `consommations_ibfk_1` FOREIGN KEY (`id_compteur`) REFERENCES `compteurs` (`id`);

--
-- Contraintes pour la table `factures`
--
ALTER TABLE `factures`
  ADD CONSTRAINT `factures_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id`);

--
-- Contraintes pour la table `predictions`
--
ALTER TABLE `predictions`
  ADD CONSTRAINT `predictions_ibfk_1` FOREIGN KEY (`id_compteur`) REFERENCES `compteurs` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
