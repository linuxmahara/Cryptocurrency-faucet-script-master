SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE `pf_ban_list` (
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `reason` varchar(150) NOT NULL DEFAULT '',
  UNIQUE KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `sf_payouts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `payout_amount` double NOT NULL,
  `payout_address` varchar(34) NOT NULL DEFAULT '',
  `timestamp` datetime NOT NULL,
  `promo_code` varchar(80) NOT NULL DEFAULT '',
  `promo_payout_amount` double NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0 ;

CREATE TABLE `sf_promo_codes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL DEFAULT '',
  `minimum_payout` double NOT NULL,
  `maximum_payout` double NOT NULL,
  `uses` int(11) NOT NULL DEFAULT '-1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0 ;

INSERT INTO `sf_promo_codes` (`id`, `code`, `minimum_payout`, `maximum_payout`, `uses`) VALUES
(11, 'double', 1, 1, -1);

CREATE TABLE `sf_staged_payments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `payout_address` varchar(34) NOT NULL DEFAULT '',
  `payout_amount` float NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0 ;