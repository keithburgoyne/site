create table ImageDimensionBinding (
	image      int not null references Image(id) on delete cascade,

	dimension  int not null references ImageDimension(id) on delete cascade,
	image_type int not null references ImageType(id),
	width      int not null,
	height     int not null,
	dpi        int not null default 72,
	filesize  int,

	primary key(image, dimension)
)

create index ImageDimensionBinding_image_index on ImageDimensionBinding(image)