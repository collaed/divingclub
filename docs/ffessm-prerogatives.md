# FFESSM Certification Levels — Prerogatives Reference (MFT / Code du Sport)

Source: Code du Sport (Articles A322-71 to A322-100) + FFESSM Manuel de Formation Technique

## Aptitudes (Prerogatives)

Each certification grants specific **aptitudes** — rights to dive at certain depths, either supervised (PE) or autonomous (PA).

| Aptitude | Meaning | Max Depth | Conditions |
|----------|---------|-----------|------------|
| PE-12 | Plongeur Encadré 12m | 12m | With guide/instructor |
| PE-20 | Plongeur Encadré 20m | 20m | With guide/instructor |
| PE-40 | Plongeur Encadré 40m | 40m | With guide/instructor |
| PE-60 | Plongeur Encadré 60m | 60m | With E4 instructor |
| PA-12 | Plongeur Autonome 12m | 12m | Autonomous (adults only) |
| PA-20 | Plongeur Autonome 20m | 20m | Autonomous (adults only) |
| PA-40 | Plongeur Autonome 40m | 40m | Autonomous (adults only) |
| PA-60 | Plongeur Autonome 60m | 60m | Autonomous (adults only) |

## Certification → Prerogatives Mapping

| Certification | Code | Prerogatives Granted | Notes |
|---------------|------|---------------------|-------|
| Niveau 1 | N1 | PE-20 | Supervised to 20m |
| PE-40 (qualification) | PE40 | PE-20 + PE-40 | Supervised to 40m |
| PA-20 (qualification) | PA20 | PE-20 + PA-20 | Autonomous to 20m |
| Niveau 2 | N2 | PE-40 + PA-20 | Supervised 40m, autonomous 20m |
| PA-40 (qualification) | PA40 | PE-40 + PA-40 | Autonomous to 40m |
| Niveau 3 | N3 | PA-60 | Autonomous to 60m (implies all lesser) |
| Niveau 4 / Guide de Palanquée | N4/GP | PA-60 + Guide | Can lead groups to 40m |
| Niveau 5 / Directeur de Plongée | N5/DP | PA-60 + DP | Can direct dive operations |
| Initiateur (E1) | E1 | PA-60 + Teach to PE-12/PE-20 | Can teach in pool/0-6m, assist in 0-20m |
| Moniteur Fédéral 1° (E2) | MF1/E2 | PA-60 + Teach to PE-20 + Guide 40m | Can teach N1, guide to 40m |
| Moniteur Fédéral 2° (E3) | MF2/E3 | PA-60 + Teach to PE-40 + Guide 60m | Can teach N2, guide to 60m |

## Dive Group (Palanquée) Rules

### Supervised Groups (Encadré)
- The group must have a qualified **guide** (Guide de Palanquée = N4/GP or higher)
- The guide determines the max depth based on the **least qualified** diver
- All divers must have aptitude for the planned depth (PE-X ≥ planned depth)
- Minors CANNOT dive autonomously

### Autonomous Groups
- All divers must be adults (18+)
- All divers must have PA-X aptitude for the planned depth
- Minimum 2 divers, maximum 3 divers per autonomous group
- Decision made by Directeur de Plongée (DP)

### Depth Limits Summary

| Group Type | Divers | Max Depth | Guide Required |
|-----------|--------|-----------|----------------|
| Supervised PE-12 | Any PE-12+ | 12m | E1 or higher |
| Supervised PE-20 | Any PE-20+ | 20m | GP/N4 or higher |
| Supervised PE-40 | Any PE-40+ | 40m | GP/N4 or higher |
| Supervised PE-60 | PE-60 holders | 60m | E4 or higher |
| Autonomous PA-12 | 2-3 adults PA-12+ | 12m | No (DP decision) |
| Autonomous PA-20 | 2-3 adults PA-20+ | 20m | No (DP decision) |
| Autonomous PA-40 | 2-3 adults PA-40+ | 40m | No (DP decision) |
| Autonomous PA-60 | 2-3 adults PA-60+ | 60m | No (DP decision) |

### Pool/Fosse (≤ 6m)
- DP minimum E1
- PE-12 divers can dive autonomously in pool
- GP/N4 can conduct baptisms

## Encadrement Levels (Teaching/Guiding)

| Level | Code | Can Teach/Guide | Depth Limit |
|-------|------|-----------------|-------------|
| Initiateur | E1 | Assist teaching in 0-6m, teach PE-12 | 6m (teach), assists to 20m |
| Moniteur Fédéral 1° | E2/MF1 | Teach N1 (PE-20), guide to 40m | 40m |
| Moniteur Fédéral 2° | E3/MF2 | Teach N2 (PE-40), guide to 60m | 60m |
| Moniteur National 1° | E4/MN1 | Teach N3, guide to 60m, PE-60 | 60m |
| Moniteur National 2° | E5/MN2 | Train instructors | 60m |

## Nitrox Qualifications

| Qualification | Code | Prerogatives |
|--------------|------|-------------|
| Plongeur Nitrox | PN-1 | Use nitrox (best mix) within air depth limits |
| Plongeur Nitrox Confirmé | PN-C | Use nitrox with independent depth calculation, teach nitrox usage |

## Equipment Requirements (Code du Sport)

### Beyond 20m (encadré) or any autonomous dive:
- Octopus (secondary air source for buddy)
- Dive computer or depth gauge + timer
- BCD with inflation

### Guide/Instructor equipment:
- Two independent air outlets + two complete regulators
- BCD
- Dive computer
- SMB (parachute de palier) per group

## Application to Club CEP Dive Groups

For the club's dive group planner, a valid palanquée requires:
1. **Supervised**: 1 qualified guide (≥ N4/GP) + 1-4 divers with PE-X ≥ planned depth
2. **Autonomous**: 2-3 adult divers with PA-X ≥ planned depth
3. **Training**: 1 qualified instructor (E-level appropriate) + students
4. **Pool (≤6m)**: E1 sufficient as DP, PE-12 divers can be autonomous

The dive group rules engine should validate:
- Each diver's aptitude covers the planned depth
- Group composition matches one of the valid patterns
- Minor restrictions are enforced
- Instructor/guide qualification matches the group type
