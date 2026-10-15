# Demie Client Portal

Separate delivery app where a photographer hands finished shoots to clients. The portal owns delivery and downloads; the WordPress site stays the brochure and curated index (see `website/CONTEXT.md` and `docs/adr/0003-portal-leaves-wordpress-portfolio-stays-curated-index.md`).

## Language

**Photographer**:
The studio owner who creates projects, uploads shoots, and invites clients. The only account that can manage anything.
_Avoid_: admin as a public term, user

**Client**:
An invited person who views and downloads their own shoots, either logged in or through a share link. Clients never create or manage anything.
_Avoid_: customer, user, account as a public term

**Project**:
A photographer's client shoot: one named collection of photos and videos with one client name, one share link, and its own view/download counts.
_Avoid_: gallery, album, order

**Share Link**:
The private URL for one project that opens its full gallery with no login. It can be rotated (old stops working) or revoked (denied instantly) and passed on to family.
_Avoid_: public link, token URL as a public term
