--
-- Table structure for table `CIForms_submissions_groups`
--

CREATE TABLE IF NOT EXISTS `CIForms_submissions_groups` (
  `id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `usergroup` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `CIForms_submissions_groups`
--
ALTER TABLE `CIForms_submissions_groups`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `CIForms_submissions_groups`
--
ALTER TABLE `CIForms_submissions_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
