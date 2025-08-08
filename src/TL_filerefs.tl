// Root
fileReferenceOrigins ctxs:Vector<Origin> = FileReferenceOrigins;

origin flags:# predicate:string is_constructor:flags.0?true action:flags.1?ActionOp noop:flags.2?string needs_parent:flags.3?string parent_is_constructor:flags.4?true = origin;

// For string => TypedOp dictionaries
typedOpArg key:string value:TypedOp = TypedOpArg;

// Actions
callOp method:string args:Vector<TypedOpArg> = ActionOp;
getMessageOp flags:# peer:TypedOp id:TypedOp from_scheduled:flags.0?TypedOp = ActionOp;

// Field extraction path
paramNotFlag = ParamFlag;
paramIsFlagAbortIfEmpty = ParamFlag;
paramIsFlagFallback fallback:TypedOp = ParamFlag;
paramIsFlagPassthrough = ParamFlag;

pathPart flags:# constructor:string param:string flag:ParamFlag = PathPart;

path parts:Vector<PathPart> = Path;
pathParent parts:Vector<PathPart> = Path;

// Typed constructors, the type is specified to simplify codegen,
// but isn't strictly necessary as it can be inferred from the TypedOpOp.
// It is fully pre-validated during the generation of the definition file.
typedOp type:string op:TypedOpOp = TypedOp;

copyOp path:Path = TypedOpOp;
copyFromParentOp path:Path = TypedOpOp;

getInputChannelByIdOp path:Path = TypedOpOp;
getInputUserByIdOp path:Path = TypedOpOp;

getInputPeerOp path:Path = TypedOpOp;
getInputUserOp path:Path = TypedOpOp;
getInputChannelOp path:Path = TypedOpOp;

getStickerSetFromDocumentAttributesOp path:Path = TypedOpOp;

// Literals & constructors (methods not allowed or needed here)
constructorOp constructor:string args:Vector<TypedOpArg> = TypedOpOp;

vectorOp values:Vector<TypedOp> = TypedOpOp;

intLiteralOp value:int = TypedOpOp;
longLiteralOp value:long = TypedOpOp;
stringLiteralOp value:string = TypedOpOp;
boolLiteralOp value:Bool = TypedOpOp;
doubleLiteralOp value:double = TypedOpOp;
themeFormatLiteralOp = TypedOpOp;
